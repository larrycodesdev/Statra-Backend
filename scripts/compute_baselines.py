"""
STATRA Personalised Baseline Computation
=========================================
Reads vital_readings from Azure SQL, computes per-patient EWMA baselines
and deviation scores, writes results to patient_baselines and
composite_deviation_scores tables.

Run:  python scripts/compute_baselines.py
Env:  DB_HOST, DB_NAME, DB_USER, DB_PASSWORD  (or copy from .env)

Dependencies: pip install pyodbc python-dotenv
"""

import os
import math
import pyodbc
from datetime import datetime, timedelta
from dotenv import load_dotenv

load_dotenv()

# ── Signal configuration ────────────────────────────────────────────────────

SIGNAL_CONFIG = {
    # signal_type: (window_days, weight, direction, min_samples, contexts)
    "temperature": (28, 3, "positive", 1500, ["any"]),
    "spo2":        (28, 3, "negative", 2000, ["any"]),
    "heart_rate":  (28, 2, "positive", 2500, ["resting", "active", "sleep"]),
    "hrv":         (21, 2, "negative", 1000, ["resting", "sleep"]),
    "steps":       (28, 1, "negative", 2000, ["any"]),
}

WEIGHT_TOTAL = sum(cfg[1] for cfg in SIGNAL_CONFIG.values())  # 11

# Status thresholds
STATUS_THRESHOLDS = [
    (9.0, "urgent"),
    (5.0, "elevated"),
    (2.0, "watch"),
    (0.0, "stable"),
]

FEVER_THRESHOLD    = 38.5
CALIBRATION_DAYS   = 28


# ── DB connection ────────────────────────────────────────────────────────────

def get_connection():
    host     = os.environ["DB_HOST"]
    database = os.environ["DB_DATABASE"]
    user     = os.environ["DB_USERNAME"]
    password = os.environ["DB_PASSWORD"]

    conn_str = (
        f"DRIVER={{ODBC Driver 18 for SQL Server}};"
        f"SERVER={host},1433;"
        f"DATABASE={database};"
        f"UID={user};PWD={password};"
        f"Encrypt=yes;TrustServerCertificate=no;"
    )
    return pyodbc.connect(conn_str)


# ── EWMA helpers ─────────────────────────────────────────────────────────────

def ewma_alpha(window_days: int, readings_per_day: float = 288.0) -> float:
    """
    Scale alpha so the EWMA half-life matches window_days in calendar time.
    288 = one reading per 5 min per day (most frequent signal).
    """
    n = window_days * readings_per_day
    return 2.0 / (n + 1.0)


def update_ewma(mean: float, variance: float, value: float, alpha: float):
    diff     = value - mean
    incr     = alpha * diff
    new_mean = mean + incr
    new_var  = (1.0 - alpha) * (variance + diff * incr)
    new_std  = math.sqrt(max(new_var, 0.0))
    return new_mean, new_var, new_std


def z_score(value: float, mean: float, stddev: float) -> float | None:
    if stddev < 1e-6:
        return None
    return (value - mean) / stddev


# ── Confidence level ─────────────────────────────────────────────────────────

def confidence_level(sample_count: int, min_samples: int) -> str:
    if sample_count >= min_samples * 2:
        return "high"
    if sample_count >= int(min_samples * 1.5):
        return "medium"
    return "low"


# ── Status from composite score ──────────────────────────────────────────────

def score_to_status(score: float) -> str:
    for threshold, status in STATUS_THRESHOLDS:
        if score >= threshold:
            return status
    return "stable"


# ── Outreach trigger check ────────────────────────────────────────────────────

def check_outreach(temp_absolute, spo2_z, total_score, contributing_signals) -> tuple[bool, str | None]:
    if temp_absolute is not None and temp_absolute >= FEVER_THRESHOLD:
        return True, f"Fever ≥{FEVER_THRESHOLD}°C — absolute threshold"
    if spo2_z is not None and spo2_z <= -2.5:
        return True, "SpO2 ≥2.5 SD below personal baseline"
    if total_score >= 9.0 and contributing_signals >= 2:
        return True, "Urgent composite score from ≥2 signals simultaneously"
    return False, None


# ── Fetch patients due for computation ───────────────────────────────────────

def fetch_active_patients(cursor):
    cursor.execute("""
        SELECT id, calibration_status, calibration_start_at,
               date_of_birth, gender
        FROM patients
        WHERE calibration_status IN ('calibrating', 'active')
    """)
    return cursor.fetchall()


# ── Fetch readings for one patient+signal+context ────────────────────────────

def fetch_readings(cursor, patient_id: int, signal_type: str,
                   context: str, window_days: int):
    since = (datetime.utcnow() - timedelta(days=window_days)).strftime("%Y-%m-%d %H:%M:%S")

    if context == "any":
        cursor.execute("""
            SELECT CAST(value AS FLOAT) as val, recorded_at
            FROM vital_readings
            WHERE patient_id = ?
              AND type = ?
              AND quality_flag = 'good'
              AND recorded_at >= ?
              AND ISNUMERIC(value) = 1
            ORDER BY recorded_at ASC
        """, patient_id, signal_type, since)
    else:
        cursor.execute("""
            SELECT CAST(value AS FLOAT) as val, recorded_at
            FROM vital_readings
            WHERE patient_id = ?
              AND type = ?
              AND activity_context = ?
              AND quality_flag = 'good'
              AND recorded_at >= ?
              AND ISNUMERIC(value) = 1
            ORDER BY recorded_at ASC
        """, patient_id, signal_type, context, since)

    return cursor.fetchall()


# ── Load existing baseline ────────────────────────────────────────────────────

def load_baseline(cursor, patient_id: int, signal_type: str, context: str):
    cursor.execute("""
        SELECT rolling_mean, rolling_variance, rolling_stddev,
               sample_count, window_days
        FROM patient_baselines
        WHERE patient_id = ? AND signal_type = ? AND activity_context = ?
    """, patient_id, signal_type, context)
    return cursor.fetchone()


# ── Upsert baseline ───────────────────────────────────────────────────────────

def upsert_baseline(cursor, patient_id, signal_type, context,
                    mean, variance, stddev, sample_count, window_days, confidence):
    cursor.execute("""
        SELECT id FROM patient_baselines
        WHERE patient_id = ? AND signal_type = ? AND activity_context = ?
    """, patient_id, signal_type, context)
    row = cursor.fetchone()

    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")

    if row:
        cursor.execute("""
            UPDATE patient_baselines
            SET rolling_mean = ?, rolling_variance = ?, rolling_stddev = ?,
                sample_count = ?, window_days = ?, baseline_confidence = ?,
                last_updated_at = ?
            WHERE patient_id = ? AND signal_type = ? AND activity_context = ?
        """, mean, variance, stddev, sample_count, window_days, confidence,
             now, patient_id, signal_type, context)
    else:
        cursor.execute("""
            INSERT INTO patient_baselines
                (patient_id, signal_type, activity_context,
                 rolling_mean, rolling_variance, rolling_stddev,
                 sample_count, window_days, baseline_confidence, last_updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, patient_id, signal_type, context,
             mean, variance, stddev, sample_count, window_days, confidence, now)


# ── Write composite score ─────────────────────────────────────────────────────

def write_composite(cursor, patient_id, signals, temp_absolute):
    # Directional z-scores — only the clinically concerning direction counts
    def directional(z, direction):
        if z is None:
            return None
        return max(z, 0) if direction == "positive" else max(-z, 0)

    z_map = {
        "temperature": signals.get("temperature", {}).get("z"),
        "spo2":        signals.get("spo2",        {}).get("z"),
        "heart_rate":  signals.get("heart_rate",  {}).get("z"),
        "hrv":         signals.get("hrv",          {}).get("z"),
        "steps":       signals.get("steps",        {}).get("z"),
    }

    contributions = {}
    total = 0.0
    contributing = 0

    for sig, (_, weight, direction, _, _) in SIGNAL_CONFIG.items():
        dz = directional(z_map.get(sig), direction)
        contrib = round(weight * dz, 4) if dz is not None else None
        contributions[sig] = contrib
        if contrib is not None:
            total += contrib
            contributing += 1

    status     = score_to_status(total)
    confidence = _lowest_confidence(signals)
    outreach, reason = check_outreach(temp_absolute, z_map.get("spo2"), total, contributing)

    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")

    cursor.execute("""
        INSERT INTO composite_deviation_scores
            (patient_id, computed_at,
             temp_z, spo2_z, hr_z, hrv_z, activity_z,
             temp_contribution, spo2_contribution, hr_contribution,
             hrv_contribution, activity_contribution,
             total_score, status, confidence,
             temperature_absolute, outreach_recommended, outreach_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """,
        patient_id, now,
        z_map.get("temperature"), z_map.get("spo2"),
        z_map.get("heart_rate"),  z_map.get("hrv"), z_map.get("steps"),
        contributions.get("temperature"), contributions.get("spo2"),
        contributions.get("heart_rate"),  contributions.get("hrv"),
        contributions.get("steps"),
        round(total, 4), status, confidence,
        temp_absolute, 1 if outreach else 0, reason,
    )


def _lowest_confidence(signals):
    order = {"low": 0, "medium": 1, "high": 2}
    confs = [s.get("confidence", "low") for s in signals.values() if s]
    if not confs:
        return "low"
    return min(confs, key=lambda c: order.get(c, 0))


# ── Update patient calibration status ────────────────────────────────────────

def update_calibration_status(cursor, patient_id, status):
    cursor.execute("""
        UPDATE patients SET calibration_status = ? WHERE id = ?
    """, status, patient_id)


# ── Main computation loop ─────────────────────────────────────────────────────

def compute_for_patient(cursor, patient):
    patient_id         = patient.id
    calibration_status = patient.calibration_status
    calibration_start  = patient.calibration_start_at

    # Start calibration clock if not started
    if calibration_start is None:
        now_str = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute("""
            UPDATE patients SET calibration_start_at = ? WHERE id = ?
        """, now_str, patient_id)
        calibration_start = datetime.utcnow()

    days_calibrating = (datetime.utcnow() - calibration_start).days
    signals = {}
    temp_absolute = None

    for signal_type, (window_days, weight, direction, min_samples, contexts) in SIGNAL_CONFIG.items():
        alpha = ewma_alpha(window_days)

        for context in contexts:
            rows = fetch_readings(cursor, patient_id, signal_type, context, window_days)
            if not rows:
                continue

            # Seed from existing baseline or first reading
            existing = load_baseline(cursor, patient_id, signal_type, context)
            if existing:
                mean, variance, stddev = existing.rolling_mean, existing.rolling_variance, existing.rolling_stddev
                sample_count = existing.sample_count
            else:
                first_val = float(rows[0].val)
                mean, variance, stddev = first_val, 0.0, 0.0
                sample_count = 0
                rows = rows[1:]

            for row in rows:
                val = float(row.val)
                mean, variance, stddev = update_ewma(mean, variance, val, alpha)
                sample_count += 1

                # Track latest temp absolute value for hard threshold check
                if signal_type == "temperature":
                    temp_absolute = val

            confidence = confidence_level(sample_count, min_samples)
            upsert_baseline(cursor, patient_id, signal_type, context,
                            mean, variance, stddev, sample_count, window_days, confidence)

            # Use primary context for z-score (first in list)
            if context == contexts[0] and stddev > 1e-6:
                latest_val = float(rows[-1].val) if rows else mean
                z = z_score(latest_val, mean, stddev)
                signals[signal_type] = {
                    "z":          z,
                    "mean":       mean,
                    "stddev":     stddev,
                    "confidence": confidence,
                }

    # Only write composite score if we have at least one signal baseline
    if signals:
        write_composite(cursor, patient_id, signals, temp_absolute)

    # Graduate from calibrating → active after 28 days
    if calibration_status == "calibrating" and days_calibrating >= CALIBRATION_DAYS:
        update_calibration_status(cursor, patient_id, "active")
        print(f"  Patient {patient_id}: calibration complete → active")


def main():
    print(f"[{datetime.utcnow().isoformat()}] Starting baseline computation...")

    conn   = get_connection()
    cursor = conn.cursor()

    patients = fetch_active_patients(cursor)
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
