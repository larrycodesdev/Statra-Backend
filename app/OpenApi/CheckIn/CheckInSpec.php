<?php

namespace App\OpenApi\CheckIn;

/**
 * @OA\Info(
 *   title="STATRA — Check-in Web App API",
 *   version="1.0.0",
 *   description="API for the STATRA Sickle Cell Check-in web app. Users log in with username + password. All check-in submissions are risk-scored **server-side** — the client sends raw inputs only, never scores.",
 *   @OA\Contact(email="api@scdwellness.app")
 * )
 * @OA\Server(url="http://localhost:8000", description="Local dev")
 * @OA\Server(url="https://api.scdwellness.app", description="Production")
 * @OA\SecurityScheme(securityScheme="bearerAuth", type="http", scheme="bearer", bearerFormat="Sanctum")
 * @OA\Tag(name="Auth",    description="Register, login, logout")
 * @OA\Tag(name="CheckIn", description="Submit and retrieve check-in records")
 */
class CheckInSpec
{
    /** @OA\Post(path="/api/v1/checkin/auth/register", tags={"Auth"},
     *   summary="Register a new check-in user (nurse, clinician, or patient self-reporting)",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"username","name","password"},
     *     @OA\Property(property="username", type="string", example="nurse01", description="Unique — used for login"),
     *     @OA\Property(property="name",     type="string", example="Sarah Adeyemi"),
     *     @OA\Property(property="password", type="string", format="password", example="pass123", minLength=6),
     *     @OA\Property(property="email",    type="string", format="email", nullable=true))),
     *   @OA\Response(response=201, description="Registered",
     *     @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="token", type="string", example="1|abc123"),
     *         @OA\Property(property="name", type="string", example="Sarah Adeyemi"),
     *         @OA\Property(property="username", type="string", example="nurse01")))),
     *   @OA\Response(response=422, description="Username already taken")) */
    public function register() {}

    /** @OA\Post(path="/api/v1/checkin/auth/login", tags={"Auth"}, summary="Login with username and password",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"username","password"},
     *     @OA\Property(property="username", type="string", example="nurse01"),
     *     @OA\Property(property="password", type="string", format="password", example="pass123"))),
     *   @OA\Response(response=200, description="Login successful",
     *     @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="token", type="string", example="1|abc123"),
     *         @OA\Property(property="name", type="string"), @OA\Property(property="username", type="string")))),
     *   @OA\Response(response=401, description="Invalid credentials")) */
    public function login() {}

    /** @OA\Post(path="/api/v1/checkin/auth/logout", tags={"Auth"}, summary="Revoke current token", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Logged out")) */
    public function logout() {}

    /** @OA\Post(path="/api/v1/checkin/check-in", tags={"CheckIn"},
     *   summary="Submit a check-in — risk score computed server-side and returned immediately",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"pid","name","genotype","meds","pain","fatigue","sleep","hydration","condition","safety"},
     *     @OA\Property(property="pid",       type="string",  example="SCD-001"),
     *     @OA\Property(property="name",      type="string",  example="Jane Johnson"),
     *     @OA\Property(property="genotype",  type="string",  enum={"SS","SC","SB+","SB0","Unknown"}, example="SS"),
     *     @OA\Property(property="meds",      type="string",  enum={"Yes","No","Missed"}, example="Yes"),
     *     @OA\Property(property="pain",      type="integer", minimum=0, maximum=10, example=7),
     *     @OA\Property(property="fatigue",   type="string",  enum={"Low","Medium","High"}, example="High"),
     *     @OA\Property(property="sleep",     type="string",  enum={"Good","Okay","Poor"}, example="Poor"),
     *     @OA\Property(property="hydration", type="string",  enum={"Good","Okay","Low"}, example="Okay"),
     *     @OA\Property(property="condition", type="string",  enum={"No, I feel normal","Slightly different","Very different"}),
     *     @OA\Property(property="safety",    type="string",  example="None",
     *       description="'None' or a red-flag symptom — triggers immediate Urgent override"),
     *     @OA\Property(property="notes",     type="string",  nullable=true),
     *     @OA\Property(property="symptoms",  type="array",   nullable=true,
     *       @OA\Items(type="string", enum={"Joint pain","Headache","Dizziness"})),
     *     @OA\Property(property="flags", type="array", nullable=true,
     *       description="Any value here triggers immediate Urgent override (score=OVERRIDE)",
     *       @OA\Items(type="string", enum={"Chest pain","Shortness of breath","Fever","Confusion or weakness","Severe chest pain","Difficulty breathing"})),
     *     @OA\Property(property="triggers", type="array", nullable=true,
     *       @OA\Items(type="string", enum={"Stress","Physical exertion","Cold weather exposure","Dehydration","Illness/infection","Poor sleep","Travel"})))),
     *   @OA\Response(response=200, description="Check-in result with computed risk score",
     *     @OA\JsonContent(
     *       @OA\Property(property="id",           type="integer", example=1),
     *       @OA\Property(property="pid",          type="string",  example="SCD-001"),
     *       @OA\Property(property="total",        type="integer", example=11, description="999 = OVERRIDE (red flag triggered)"),
     *       @OA\Property(property="displayScore", type="string",  example="11", description="'OVERRIDE' or stringified total"),
     *       @OA\Property(property="status",       type="string",  enum={"Stable","Watch closely","Elevated","Urgent"}),
     *       @OA\Property(property="redFlag",      type="boolean", example=false),
     *       @OA\Property(property="reason",       type="string",  example="Pain level is the primary risk driver today"),
     *       @OA\Property(property="genoMult",     type="number",  format="float", example=1.2,
     *         description="SS/SB0=1.2, SB+=1.1, others=1.0"),
     *       @OA\Property(property="scores", type="object",
     *         @OA\Property(property="pain", type="number", example=5),
     *         @OA\Property(property="fatigue", type="number", example=2),
     *         @OA\Property(property="sleep", type="number", example=1),
     *         @OA\Property(property="hydration", type="number", example=1),
     *         @OA\Property(property="symptoms", type="number", example=5),
     *         @OA\Property(property="triggers", type="number", example=2)),
     *       @OA\Property(property="ts", type="string", format="date-time"))),
     *   @OA\Response(response=422, description="Validation error")) */
    public function store() {}

    /** @OA\Get(path="/api/v1/checkin/history", tags={"CheckIn"},
     *   summary="Full check-in history for the logged-in user, newest first",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="List of check-in records")) */
    public function history() {}

    /** @OA\Get(path="/api/v1/checkin/check-in/latest", tags={"CheckIn"},
     *   summary="Most recent check-in result",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Latest check-in"),
     *   @OA\Response(response=404, description="No check-ins yet")) */
    public function latest() {}
}
