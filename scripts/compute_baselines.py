"""
STATRA Personalised Baseline Computation
=========================================
Implements the computation logic defined in:
"STATRA — Personalised Baseline Modelling v2.0 (Clinically Reviewed)"

Reads vital_readings, computes per-patient EWMA baselines, writes
deviation_scores (§4.4) and composite_deviation_scores (§4.5).

NOTE: deviation_scores table must exist before running. See migration below:

    CREATE TABLE deviation_scores (
        id               BIGINT IDENTITY(1,1) PRIMARY KEY,
        patient_id       BIGINT NOT NULL,
        signal_type      VARCHAR(20) NOT NULL,
        reading_value    FLOAT NOT NULL,
        z_score          FLOAT NOT NULL,
        quality_flag     VARCHAR(20) NOT NULL DEFAULT 'good',
        scored_at        DATETIME NOT NULL,
        composite_score_id BIGINT NULL
    );

Run:  python compute_baselines.py
Env:  DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD

Dependencies: pip install pyodbc python-dotenv
"""

import os
import math
import time
import pyodbc
from datetime import datetime, timedelta
from dotenv import load_dotenv

load_dotenv()

# ── Signal configuration ──────────────────────────────────────────────────────
# (window_days, weight, direction, min_samples, contexts)
# NOTE: spec §3 calls the 5th signal "activity". DB/ingestion stores it as "steps".

SIGNAL_CONFIG = {
    "temperature": (28, 3, "positive", 1500, ["any"]),
    "spo2":        (28, 3, "negative", 2000, ["any"]),
    "heart_rate":  (28, 2, "positive", 2500, ["resting", "light", "active", "sleep"]),
    "hrv":         (21, 2, "negative", 1000, ["resting", "sleep"]),
    "steps":       (28, 1, "negative", 2000, ["any"]),
}

# §7.3 — Status thresholds
STATUS_THRESHOLDS = [
    (9.0, "urgent"),
    (5.0, "elevated"),
    (2.0, "watch"),
    (0.0, "stable"),
]

# §4.1 — Age group boundaries
AGE_GROUPS = [
    (0,   2,   "infant"),
    (2,   12,  "child"),
    (12,  18,  "adolescent"),
    (18,  999, "adult"),
]

FEVER_THRESHOLD    = 38.5  # §7.4
CALIBRATION_DAYS   = 28    # §6.1
GAP_STALE_HOURS    = 12    # §6.3
GAP_RESTAB_HOURS   = 48    # §6.3
GAP_RESTAB_MIN_ROWS = 100  # §6.3 — minimum good readings to confirm re-stabilisation
SPO2_SUSTAINED_MIN = 30    # §7.5 — minutes
SPO2_SUSTAINED_SD  = 2.5   # §7.5 — z-score threshold
PERSIST_ELEV_HOURS = 4     # §7.5 — hours
PERSIST_ELEV_SCORE = 5.0   # §7.5 — elevated threshold


# ── DB connection ─────────────────────────────────────────────────────────────

def get_connection(retries: int = 4, delay: int = 40):
    conn_str = (
        "DRIVER={ODBC Driver 18 for SQL Server};"
        f"SERVER={os.environ['DB_HOST']},1433;"
        f"DATABASE={os.environ['DB_DATABASE']};"
        f"UID={os.environ['DB_USERNAME']};"
        f"PWD={os.environ['DB_PASSWORD']};"
        "Encrypt=yes;TrustServerCertificate=no;"
        "Connection Timeout=30;"
    )
    for attempt in range(1, retries + 1):
        try:
            return pyodbc.connect(conn_str)
        except pyodbc.OperationalError as e:
            if attempt == retries:
                raise
            print(f"  DB connection attempt {attempt} failed — Azure may be waking up. Retrying in {delay}s...")
            time.sleep(delay)


# ── Age group — §4.1 ─────────────────────────────────────────────────────────
# Always computed fresh from DOB. Never read from the DB column — it goes stale
# as patients age and the column is only set at registration.

def age_group_from_dob(dob) -> str:
    if dob is None:
        return "adult"
    age = (datetime.utcnow().date() - dob).days // 365
    for lo, hi, group in AGE_GROUPS:
        if lo <= age < hi:
            return group
    return "adult"


# ── EWMA — §5.2 ──────────────────────────────────────────────────────────────

def ewma_alpha(window_days: int, readings_per_day: float = 288.0) -> float:
    """Scale α so EWMA half-life matches window_days in calendar time."""
    return 2.0 / (window_days * readings_per_day + 1.0)


def update_ewma(mean: float, variance: float, value: float, alpha: float):
    diff     = value - mean
    incr     = alpha * diff
    new_mean = mean + incr
    new_var  = (1.0 - alpha) * (variance + diff * incr)
    new_std  = math.sqrt(max(new_var, 0.0))
    return new_mean, new_var, new_std


def z_score(value: float, mean: float, stddev: float):
    if stddev < 1e-6:
        return None
    return (value - mean) / stddev


# ── Directional z-score — §7.1 ───────────────────────────────────────────────

def directional_z(z, direction: str, signal: str, age_group: str):
    if z is None:
        return None
    if signal == "steps":
        # §7.1: children — reduced activity only (early warning sign)
        #       adults  — both directions informative
        if age_group in ("infant", "child"):
            return max(-z, 0)
        return abs(z)
    if direction == "positive":
        return max(z, 0)
    return max(-z, 0)


# ── Confidence — §6.2 ────────────────────────────────────────────────────────

def confidence_level(sample_count: int, min_samples: int) -> str:
    if sample_count >= min_samples * 2:
        return "high"
    if sample_count >= int(min_samples * 1.5):
        return "medium"
    return "low"


def lowest_confidence(confidences: list) -> str:
    order = {"low": 0, "medium": 1, "high": 2}
    if not confidences:
        return "low"
    return min(confidences, key=lambda c: order.get(c, 0))


def score_to_status(score: float) -> str:
    for threshold, status in STATUS_THRESHOLDS:
        if score >= threshold:
            return status
    return "stable"


# ── Gap / stale detection — §6.3 ─────────────────────────────────────────────

def get_data_gap_hours(cursor, patient_id: int):
    """Hours since last received vital reading, or None if no readings."""
    cursor.execute("""
        SELECT MAX(received_at) AS last_received
        FROM vital_readings
        WHERE patient_id = ?
    """, patient_id)
    row = cursor.fetchone()
    if not row or row.last_received is None:
        return None
    return (datetime.utcnow() - row.last_received).total_seconds() / 3600.0


def handle_gap(cursor, patient, gap_hours) -> bool:
    """
    §6.3 — apply gap rules.
    Returns True if scoring should be skipped (stale or re-stabilising).
    Baselines continue to update regardless; only scoring is gated.
    """
    patient_id = patient.id

    if gap_hours is None:
        return True  # No readings at all — stay calibrating, don't score

    if gap_hours > GAP_STALE_HOURS:
        if patient.calibration_status != "stale":
            cursor.execute(
                "UPDATE patients SET calibration_status='stale' WHERE id=?",
                patient_id
            )
            print(f"  Patient {patient_id}: flagged stale (gap {gap_hours:.1f}h > {GAP_STALE_HOURS}h)")
        return True

    # Gap ≤ 12h — if patient was stale, check 48h re-stabilisation window
    if patient.calibration_status == "stale":
        since = (datetime.utcnow() - timedelta(hours=GAP_RESTAB_HOURS)).strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute("""
            SELECT COUNT(*) AS cnt
            FROM vital_readings
            WHERE patient_id = ? AND received_at >= ? AND quality_flag = 'good'
        """, patient_id, since)
        if cursor.fetchone().cnt >= GAP_RESTAB_MIN_ROWS:
            cursor.execute(
                "UPDATE patients SET calibration_status='active' WHERE id=?",
                patient_id
            )
            print(f"  Patient {patient_id}: re-stabilised → active")
            return False
        return True  # Still within re-stabilisation window

    return False


# ── Crisis reset — §6.1 ───────────────────────────────────────────────────────

def reset_calibration_on_crisis(cursor, patient):
    """
    §6.1: if a critical alert fired during the calibration window, reset the clock.
    A crisis during calibration corrupts the baseline — must restart.
    """
    if patient.calibration_status != "calibrating":
        return
    if patient.calibration_start_at is None:
        return

    cal_start_str = patient.calibration_start_at.strftime("%Y-%m-%d %H:%M:%S")
    cursor.execute("""
        SELECT MAX(created_at) AS last_crisis
        FROM alerts
        WHERE patient_id = ? AND level = 1 AND created_at > ?
    """, patient.id, cal_start_str)
    row = cursor.fetchone()
    if row and row.last_crisis is not None:
        now_str = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute(
            "UPDATE patients SET calibration_start_at=? WHERE id=?",
            now_str, patient.id
        )
        print(f"  Patient {patient.id}: crisis during calibration — clock reset to now")


# ── Outreach triggers — §7.5 ─────────────────────────────────────────────────

def check_sustained_spo2_drop(cursor, patient_id: int, baseline_mean: float, baseline_std: float) -> bool:
    """
    §7.5 Trigger 2: SpO2 ≥2.5 SD below personal baseline for ≥30 consecutive minutes.
    """
    if baseline_std < 1e-6:
        return False

    since = (datetime.utcnow() - timedelta(minutes=SPO2_SUSTAINED_MIN + 30)).strftime("%Y-%m-%d %H:%M:%S")
    cursor.execute("""
        SELECT TRY_CAST(value AS FLOAT) AS val, recorded_at
        FROM vital_readings
        WHERE patient_id = ? AND type = 'spo2' AND quality_flag = 'good'
          AND recorded_at >= ?
        ORDER BY recorded_at ASC
    """, patient_id, since)
    rows = [r for r in cursor.fetchall() if r.val is not None]

    if not rows:
        return False

    window_start = None
    for row in rows:
        z = (float(row.val) - baseline_mean) / baseline_std
        if z <= -SPO2_SUSTAINED_SD:
            if window_start is None:
                window_start = row.recorded_at
            elif (row.recorded_at - window_start).total_seconds() / 60.0 >= SPO2_SUSTAINED_MIN:
                return True
        else:
            window_start = None

    return False


def check_persistent_elevation(cursor, patient_id: int) -> bool:
    """
    §7.5 Trigger 4: composite score ≥5 sustained over ≥4 consecutive hours.
    """
    since = (datetime.utcnow() - timedelta(hours=PERSIST_ELEV_HOURS)).strftime("%Y-%m-%d %H:%M:%S")
    cursor.execute("""
        SELECT total_score, computed_at
        FROM composite_deviation_scores
        WHERE patient_id = ? AND computed_at >= ?
        ORDER BY computed_at ASC
    """, patient_id, since)
    rows = cursor.fetchall()

    if not rows:
        return False

    if not all(r.total_score >= PERSIST_ELEV_SCORE for r in rows):
        return False

    span_hours = (rows[-1].computed_at - rows[0].computed_at).total_seconds() / 3600.0
    return span_hours >= PERSIST_ELEV_HOURS


def check_outreach(cursor, patient_id, temp_absolute,
                   spo2_baseline_mean, spo2_baseline_std,
                   total_score, contributing):
    """§7.5 — all four outreach triggers in priority order."""

    # Trigger 1: absolute fever — §7.4
    if temp_absolute is not None and temp_absolute >= FEVER_THRESHOLD:
        return True, f"Fever ≥{FEVER_THRESHOLD}°C — absolute threshold"

    # Trigger 2: sustained SpO2 drop ≥30 min
    if spo2_baseline_mean is not None:
        if check_sustained_spo2_drop(cursor, patient_id, spo2_baseline_mean, spo2_baseline_std):
            return True, (
                f"SpO2 ≥{SPO2_SUSTAINED_SD} SD below personal baseline "
                f"for ≥{SPO2_SUSTAINED_MIN} consecutive minutes"
            )

    # Trigger 3: urgent composite from ≥2 signals simultaneously
    if total_score >= 9.0 and contributing >= 2:
        return True, "Urgent composite score from ≥2 signals simultaneously"

    # Trigger 4: persistent elevation ≥4 hours
    if check_persistent_elevation(cursor, patient_id):
        return True, f"Elevated status sustained ≥{PERSIST_ELEV_HOURS} hours without remission"

    return False, None


# ── DB reads ──────────────────────────────────────────────────────────────────

def fetch_patients(cursor):
    # Include 'stale' so we can handle gap recovery (§6.3)
    cursor.execute("""
        SELECT id, calibration_status, calibration_start_at, date_of_birth, genotype
        FROM patients
        WHERE calibration_status IN ('calibrating', 'active', 'stale')
    """)
    return cursor.fetchall()


def fetch_readings(cursor, patient_id, signal_type, context, window_days):
    since = (datetime.utcnow() - timedelta(days=window_days)).strftime("%Y-%m-%d %H:%M:%S")
    if context == "any":
        cursor.execute("""
            SELECT TRY_CAST(value AS FLOAT) AS val, recorded_at
            FROM vital_readings
            WHERE patient_id = ? AND type = ? AND quality_flag = 'good'
              AND recorded_at >= ?
            ORDER BY recorded_at ASC
        """, patient_id, signal_type, since)
    else:
        cursor.execute("""
            SELECT TRY_CAST(value AS FLOAT) AS val, recorded_at
            FROM vital_readings
            WHERE patient_id = ? AND type = ? AND activity_context = ?
              AND quality_flag = 'good' AND recorded_at >= ?
            ORDER BY recorded_at ASC
        """, patient_id, signal_type, context, since)
    return [r for r in cursor.fetchall() if r.val is not None]


def load_baseline(cursor, patient_id, signal_type, context):
    cursor.execute("""
        SELECT rolling_mean, rolling_variance, rolling_stddev, sample_count
        FROM patient_baselines
        WHERE patient_id = ? AND signal_type = ? AND activity_context = ?
    """, patient_id, signal_type, context)
    return cursor.fetchone()


# ── DB writes ─────────────────────────────────────────────────────────────────

def upsert_baseline(cursor, patient_id, signal_type, context,
                    mean, variance, stddev, sample_count, window_days, confidence):
    cursor.execute("""
        SELECT id FROM patient_baselines
        WHERE patient_id = ? AND signal_type = ? AND activity_context = ?
    """, patient_id, signal_type, context)
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    if cursor.fetchone():
        cursor.execute("""
            UPDATE patient_baselines
            SET rolling_mean=?, rolling_variance=?, rolling_stddev=?,
                sample_count=?, window_days=?, baseline_confidence=?, last_updated_at=?
            WHERE patient_id=? AND signal_type=? AND activity_context=?
        """, mean, variance, stddev, sample_count, window_days, confidence,
             now, patient_id, signal_type, context)
    else:
        cursor.execute("""
            INSERT INTO patient_baselines
                (patient_id, signal_type, activity_context, rolling_mean, rolling_variance,
                 rolling_stddev, sample_count, window_days, baseline_confidence, last_updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        """, patient_id, signal_type, context, mean, variance, stddev,
             sample_count, window_days, confidence, now)


def write_composite(cursor, patient_id, signals, temp_absolute,
                    spo2_baseline_mean, spo2_baseline_std) -> int:
    """
    §7.2 / §7.4 — write composite score. Returns the inserted row id for
    linking deviation_scores (§4.4).

    §7.4 hard override: fever ≥38.5°C → status=urgent, skip weighted scoring entirely.
    """
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")

    # §7.4 — temperature hard override (must short-circuit normal scoring)
    if temp_absolute is not None and temp_absolute >= FEVER_THRESHOLD:
        cursor.execute("""
            INSERT INTO composite_deviation_scores
                (patient_id, computed_at,
                 temp_z, spo2_z, hr_z, hrv_z, activity_z,
                 temp_contribution, spo2_contribution, hr_contribution,
                 hrv_contribution, activity_contribution,
                 total_score, status, confidence,
                 temperature_absolute, outreach_recommended, outreach_reason)
            OUTPUT INSERTED.id
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        """,
            patient_id, now,
            signals.get("temperature", {}).get("z"),
            signals.get("spo2",        {}).get("z"),
            signals.get("heart_rate",  {}).get("z"),
            signals.get("hrv",         {}).get("z"),
            signals.get("steps",       {}).get("z"),
            None, None, None, None, None,
            99.0, "urgent", "low",
            temp_absolute, 1,
            f"Fever ≥{FEVER_THRESHOLD}°C — absolute threshold (§7.4)"
        )
        return cursor.fetchone()[0]

    # Normal weighted scoring — §7.2
    z_map         = {s: signals[s]["z"] for s in signals}
    confs         = [signals[s]["confidence"] for s in signals]
    total         = 0.0
    contributing  = 0
    contributions = {}

    for sig, (_, weight, _, _, _) in SIGNAL_CONFIG.items():
        dz      = signals.get(sig, {}).get("directional_z")
        contrib = round(weight * dz, 4) if dz is not None else None
        contributions[sig] = contrib
        if contrib is not None:
            total        += contrib
            contributing += 1

    status   = score_to_status(total)
    conf     = lowest_confidence(confs)
    outreach, reason = check_outreach(
        cursor, patient_id, temp_absolute,
        spo2_baseline_mean, spo2_baseline_std,
        total, contributing
    )

    cursor.execute("""
        INSERT INTO composite_deviation_scores
            (patient_id, computed_at,
             temp_z, spo2_z, hr_z, hrv_z, activity_z,
             temp_contribution, spo2_contribution, hr_contribution,
             hrv_contribution, activity_contribution,
             total_score, status, confidence,
             temperature_absolute, outreach_recommended, outreach_reason)
        OUTPUT INSERTED.id
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    """,
        patient_id, now,
        z_map.get("temperature"), z_map.get("spo2"),
        z_map.get("heart_rate"),  z_map.get("hrv"), z_map.get("steps"),
        contributions.get("temperature"), contributions.get("spo2"),
        contributions.get("heart_rate"),  contributions.get("hrv"),
        contributions.get("steps"),
        round(total, 4), status, conf,
        temp_absolute, 1 if outreach else 0, reason,
    )
    return cursor.fetchone()[0]


def write_deviation_scores(cursor, patient_id, signals, composite_id: int):
    """§4.4 — one row per signal per computation run."""
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    for sig, data in signals.items():
        if data.get("z") is None:
            continue
        cursor.execute("""
            INSERT INTO deviation_scores
                (patient_id, signal_type, reading_value, z_score,
                 quality_flag, scored_at, composite_score_id)
            VALUES (?,?,?,?,?,?,?)
        """,
            patient_id, sig,
            data["latest_val"], round(data["z"], 6),
            "good", now, composite_id
        )


# ── Per-patient computation ───────────────────────────────────────────────────

def compute_for_patient(cursor, patient):
    patient_id = patient.id

    # §6.3 — gap / stale detection first; stale patients skip scoring
    gap_hours = get_data_gap_hours(cursor, patient_id)
    if handle_gap(cursor, patient, gap_hours):
        return

    # §6.1 — always compute age_group fresh from DOB (stale DB column ignored)
    age_grp = age_group_from_dob(
        patient.date_of_birth.date() if patient.date_of_birth else None
    )

    # Initialise calibration_start_at if missing
    cal_start = patient.calibration_start_at
    if cal_start is None:
        now_str = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute(
            "UPDATE patients SET calibration_start_at=? WHERE id=?",
            now_str, patient_id
        )
        cal_start = datetime.utcnow()

    # §6.1 — reset clock if a critical alert fired during calibration
    reset_calibration_on_crisis(cursor, patient)
    cursor.execute("SELECT calibration_start_at FROM patients WHERE id=?", patient_id)
    cal_start = cursor.fetchone().calibration_start_at or datetime.utcnow()

    days_calibrating  = (datetime.utcnow() - cal_start).days
    is_calibrating    = patient.calibration_status == "calibrating"

    signals            = {}
    temp_absolute      = None
    spo2_baseline_mean = None
    spo2_baseline_std  = None

    for signal_type, (window_days, weight, direction, min_samples, contexts) in SIGNAL_CONFIG.items():
        alpha = ewma_alpha(window_days)

        for context in contexts:
            rows = fetch_readings(cursor, patient_id, signal_type, context, window_days)
            if not rows:
                continue

            existing = load_baseline(cursor, patient_id, signal_type, context)
            if existing:
                mean         = existing.rolling_mean
                variance     = existing.rolling_variance
                stddev       = existing.rolling_stddev
                sample_count = existing.sample_count
            else:
                mean         = float(rows[0].val)
                variance     = 0.0
                stddev       = 0.0
                sample_count = 0
                rows         = rows[1:]

            latest_val = mean
            for row in rows:
                val      = float(row.val)
                mean, variance, stddev = update_ewma(mean, variance, val, alpha)
                sample_count += 1
                latest_val    = val
                if signal_type == "temperature":
                    temp_absolute = val

            confidence = confidence_level(sample_count, min_samples)

            # §6.1 — always update baselines even during calibration
            upsert_baseline(cursor, patient_id, signal_type, context,
                            mean, variance, stddev, sample_count, window_days, confidence)

            # Retain SpO2 baseline stats for the sustained-drop outreach check
            if signal_type == "spo2" and context == "any":
                spo2_baseline_mean = mean
                spo2_baseline_std  = stddev

            # §5.4 — only the first (primary) context feeds the composite z-score
            if context == contexts[0]:
                z  = z_score(latest_val, mean, stddev)
                dz = directional_z(z, direction, signal_type, age_grp)
                signals[signal_type] = {
                    "z":             z,
                    "directional_z": dz,
                    "confidence":    confidence,
                    "latest_val":    latest_val,
                }

    if not signals:
        return

    # §6.1 — do NOT write scores during calibration period
    if is_calibrating:
        if days_calibrating >= CALIBRATION_DAYS:
            # Both time AND sample-count thresholds must be met (§6.1)
            all_confident = all(
                signals.get(sig, {}).get("confidence") in ("medium", "high")
                for sig in SIGNAL_CONFIG
                if sig in signals
            )
            if all_confident:
                cursor.execute(
                    "UPDATE patients SET calibration_status='active' WHERE id=?",
                    patient_id
                )
                print(f"  Patient {patient_id}: calibration complete → active")
        return  # No scores written while calibrating

    # §7.2 / §7.4 — write composite score
    composite_id = write_composite(
        cursor, patient_id, signals, temp_absolute,
        spo2_baseline_mean, spo2_baseline_std
    )

    # §4.4 — write per-signal deviation scores, linked to composite
    write_deviation_scores(cursor, patient_id, signals, composite_id)


# ── Entry point ───────────────────────────────────────────────────────────────

def main():
    print(f"[{datetime.utcnow().isoformat()}] Starting baseline computation...")
    conn   = get_connection()
    cursor = conn.cursor()

    patients = fetch_patients(cursor)
    print(f"Processing {len(patients)} patients")

    for patient in patients:
        try:
            compute_for_patient(cursor, patient)
            conn.commit()
            print(f"  Patient {patient.id}: done")
        except Exception as e:
            conn.rollback()
            print(f"  Patient {patient.id}: ERROR — {e}")

    cursor.close()
    conn.close()
    print(f"[{datetime.utcnow().isoformat()}] Done.")


if __name__ == "__main__":
    main()
