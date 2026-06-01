<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *   title="STATRA — Check-in Web App API",
 *   version="1.0.0",
 *   description="REST API for the STATRA Sickle Cell Check-in web app. Users authenticate with username + password. All check-in submissions are risk-scored server-side — the client sends raw inputs only.",
 *   @OA\Contact(email="api@scdwellness.app")
 * )
 *
 * @OA\Server(url="http://localhost:8000", description="Local dev")
 * @OA\Server(url="https://api.scdwellness.app", description="Production")
 *
 * @OA\SecurityScheme(
 *   securityScheme="bearerAuth",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="Sanctum"
 * )
 *
 * @OA\Tag(name="Auth",    description="Register, login, logout")
 * @OA\Tag(name="CheckIn", description="Submit and retrieve check-in records")
 */

// ═══════════════════════════════════════════════════════════════
// AUTH
// ═══════════════════════════════════════════════════════════════

/**
 * @OA\Post(
 *   path="/api/v1/checkin/auth/register",
 *   tags={"Auth"},
 *   summary="Register a new check-in user (nurse, clinician, or patient)",
 *   @OA\RequestBody(required=true,
 *     @OA\JsonContent(
 *       required={"username","name","password"},
 *       @OA\Property(property="username", type="string", example="nurse01", description="Unique username — used for login"),
 *       @OA\Property(property="name",     type="string", example="Sarah Adeyemi"),
 *       @OA\Property(property="password", type="string", format="password", example="pass123", minLength=6),
 *       @OA\Property(property="email",    type="string", format="email", nullable=true, example="sarah@hospital.ng")
 *     )
 *   ),
 *   @OA\Response(response=201, description="Registered successfully",
 *     @OA\JsonContent(
 *       @OA\Property(property="success", type="boolean", example=true),
 *       @OA\Property(property="message", type="string",  example="Registered successfully."),
 *       @OA\Property(property="data",    type="object",
 *         @OA\Property(property="token",    type="string", example="1|abc123..."),
 *         @OA\Property(property="name",     type="string", example="Sarah Adeyemi"),
 *         @OA\Property(property="username", type="string", example="nurse01")
 *       )
 *     )
 *   ),
 *   @OA\Response(response=422, description="Username already taken",
 *     @OA\JsonContent(
 *       @OA\Property(property="success", type="boolean", example=false),
 *       @OA\Property(property="message", type="string",  example="Validation failed."),
 *       @OA\Property(property="errors",  type="object",
 *         @OA\Property(property="username", type="array", @OA\Items(type="string", example="The username has already been taken."))
 *       )
 *     )
 *   )
 * )
 */

/**
 * @OA\Post(
 *   path="/api/v1/checkin/auth/login",
 *   tags={"Auth"},
 *   summary="Login with username and password",
 *   @OA\RequestBody(required=true,
 *     @OA\JsonContent(
 *       required={"username","password"},
 *       @OA\Property(property="username", type="string", example="nurse01"),
 *       @OA\Property(property="password", type="string", format="password", example="pass123")
 *     )
 *   ),
 *   @OA\Response(response=200, description="Login successful",
 *     @OA\JsonContent(
 *       @OA\Property(property="success", type="boolean", example=true),
 *       @OA\Property(property="message", type="string",  example="Login successful."),
 *       @OA\Property(property="data",    type="object",
 *         @OA\Property(property="token",    type="string", example="1|abc123..."),
 *         @OA\Property(property="name",     type="string", example="Sarah Adeyemi"),
 *         @OA\Property(property="username", type="string", example="nurse01")
 *       )
 *     )
 *   ),
 *   @OA\Response(response=401, description="Invalid username or password",
 *     @OA\JsonContent(
 *       @OA\Property(property="success", type="boolean", example=false),
 *       @OA\Property(property="message", type="string",  example="Invalid username or password.")
 *     )
 *   )
 * )
 */

/**
 * @OA\Post(
 *   path="/api/v1/checkin/auth/logout",
 *   tags={"Auth"},
 *   summary="Revoke current token",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Logged out",
 *     @OA\JsonContent(
 *       @OA\Property(property="success", type="boolean", example=true),
 *       @OA\Property(property="message", type="string",  example="Logged out.")
 *     )
 *   )
 * )
 */

// ═══════════════════════════════════════════════════════════════
// CHECK-IN
// ═══════════════════════════════════════════════════════════════

/**
 * @OA\Post(
 *   path="/api/v1/checkin/check-in",
 *   tags={"CheckIn"},
 *   summary="Submit a new check-in — risk score calculated server-side",
 *   security={{"bearerAuth":{}}},
 *   @OA\RequestBody(required=true,
 *     @OA\JsonContent(
 *       required={"pid","name","genotype","meds","pain","fatigue","sleep","hydration","condition","safety"},
 *       @OA\Property(property="pid",       type="string",  example="SCD-001",  description="Patient ID (hospital or internal)"),
 *       @OA\Property(property="name",      type="string",  example="Jane Johnson"),
 *       @OA\Property(property="genotype",  type="string",  enum={"SS","SC","SB+","SB0","Unknown"}, example="SS"),
 *       @OA\Property(property="meds",      type="string",  enum={"Yes","No","Missed"}, example="Yes"),
 *       @OA\Property(property="pain",      type="integer", minimum=0, maximum=10, example=7),
 *       @OA\Property(property="fatigue",   type="string",  enum={"Low","Medium","High"}, example="High"),
 *       @OA\Property(property="sleep",     type="string",  enum={"Good","Okay","Poor"}, example="Poor"),
 *       @OA\Property(property="hydration", type="string",  enum={"Good","Okay","Low"}, example="Okay"),
 *       @OA\Property(property="condition", type="string",
 *         enum={"No, I feel normal","Slightly different","Very different"}, example="Very different"),
 *       @OA\Property(property="safety",    type="string",  example="None",
 *         description="'None' or the name of a red-flag safety symptom — immediately triggers Urgent status"),
 *       @OA\Property(property="notes",     type="string",  nullable=true, example="Patient mentioned recent travel"),
 *       @OA\Property(property="symptoms",  type="array",   nullable=true,
 *         @OA\Items(type="string", enum={"Joint pain","Headache","Dizziness"})
 *       ),
 *       @OA\Property(property="flags",     type="array",   nullable=true,
 *         description="Red-flag symptoms — any value here triggers immediate Urgent override",
 *         @OA\Items(type="string", enum={"Chest pain","Shortness of breath","Fever","Confusion or weakness","Severe chest pain","Difficulty breathing"})
 *       ),
 *       @OA\Property(property="triggers",  type="array",   nullable=true,
 *         @OA\Items(type="string", enum={"Stress","Physical exertion","Cold weather exposure","Dehydration","Illness/infection","Poor sleep","Travel"})
 *       )
 *     )
 *   ),
 *   @OA\Response(response=200, description="Check-in submitted — risk result returned",
 *     @OA\JsonContent(
 *       @OA\Property(property="id",           type="integer", example=1),
 *       @OA\Property(property="pid",          type="string",  example="SCD-001"),
 *       @OA\Property(property="name",         type="string",  example="Jane Johnson"),
 *       @OA\Property(property="total",        type="integer", example=11,
 *         description="Raw risk score (999 = OVERRIDE/Urgent due to red flag)"),
 *       @OA\Property(property="displayScore", type="string",  example="11",
 *         description="'OVERRIDE' if red flag, otherwise the stringified total"),
 *       @OA\Property(property="status",       type="string",
 *         enum={"Stable","Watch closely","Elevated","Urgent"}, example="Elevated"),
 *       @OA\Property(property="redFlag",      type="boolean", example=false),
 *       @OA\Property(property="reason",       type="string",  example="Pain level is the primary risk driver today"),
 *       @OA\Property(property="genoMult",     type="number",  format="float", example=1.2,
 *         description="Genotype multiplier applied — SS/SB0=1.2, SB+=1.1, others=1.0"),
 *       @OA\Property(property="scores",       type="object",
 *         @OA\Property(property="pain",      type="number", example=5),
 *         @OA\Property(property="fatigue",   type="number", example=2),
 *         @OA\Property(property="sleep",     type="number", example=1),
 *         @OA\Property(property="hydration", type="number", example=1),
 *         @OA\Property(property="symptoms",  type="number", example=5),
 *         @OA\Property(property="triggers",  type="number", example=2)
 *       ),
 *       @OA\Property(property="ts", type="string", format="date-time", example="2026-05-31T10:00:00Z")
 *     )
 *   ),
 *   @OA\Response(response=422, description="Validation error")
 * )
 */

/**
 * @OA\Get(
 *   path="/api/v1/checkin/history",
 *   tags={"CheckIn"},
 *   summary="Get full check-in history for the logged-in user, newest first",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="List of check-in records",
 *     @OA\JsonContent(
 *       type="array",
 *       @OA\Items(
 *         @OA\Property(property="id",           type="integer"),
 *         @OA\Property(property="pid",          type="string"),
 *         @OA\Property(property="name",         type="string"),
 *         @OA\Property(property="total",        type="integer"),
 *         @OA\Property(property="displayScore", type="string"),
 *         @OA\Property(property="status",       type="string"),
 *         @OA\Property(property="redFlag",      type="boolean"),
 *         @OA\Property(property="ts",           type="string", format="date-time")
 *       )
 *     )
 *   )
 * )
 */

/**
 * @OA\Get(
 *   path="/api/v1/checkin/check-in/latest",
 *   tags={"CheckIn"},
 *   summary="Get the most recent check-in result for the logged-in user",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Latest check-in record"),
 *   @OA\Response(response=404, description="No check-ins yet",
 *     @OA\JsonContent(
 *       @OA\Property(property="success", type="boolean", example=false),
 *       @OA\Property(property="message", type="string",  example="No check-ins yet.")
 *     )
 *   )
 * )
 */
class CheckInSpec {}
