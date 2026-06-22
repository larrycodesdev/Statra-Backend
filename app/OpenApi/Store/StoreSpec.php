<?php

namespace App\OpenApi\Store;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'STATRA — Band Store API',
    version: '1.0.0',
    description: 'API for the STATRA Band ordering website. Handles product info, order placement via Korapay, order tracking, and admin order management.',
    contact: new OA\Contact(email: 'api@scdwellness.app')
)]
#[OA\Server(url: 'https://statrawebapp-f7gaa7bghfczhmf7.centralus-01.azurewebsites.net', description: 'Production')]
#[OA\Server(url: 'http://localhost:8000', description: 'Local dev')]
#[OA\SecurityScheme(securityScheme: 'adminToken', type: 'http', scheme: 'bearer', description: 'Static STORE_ADMIN_TOKEN from server .env')]
/**
 * @OA\Tag(name="Product",     description="Product info")
 * @OA\Tag(name="Orders",      description="Place and track orders")
 * @OA\Tag(name="Payment",     description="Korapay webhook")
 * @OA\Tag(name="Admin",       description="Admin order management — requires Bearer token")
 */
class StoreSpec
{
    /** @OA\Get(path="/api/v1/store/product", tags={"Product"},
     *   summary="Get STATRA Band product info (price, sizes, plans)",
     *   @OA\Response(response=200, description="Product details",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="name",           type="string",  example="STATRA Band"),
     *         @OA\Property(property="price",          type="number",  example=149.00),
     *         @OA\Property(property="original_price", type="number",  example=199.00),
     *         @OA\Property(property="currency",       type="string",  example="USD"),
     *         @OA\Property(property="plans",  type="array",  @OA\Items(type="object")),
     *         @OA\Property(property="sizes",  type="array",  @OA\Items(type="object")),
     *         @OA\Property(property="badges", type="array",  @OA\Items(type="string"))))) */
    public function product() {}

    /** @OA\Post(path="/api/v1/store/orders", tags={"Orders"},
     *   summary="Place an order — returns a Korapay checkout URL to redirect the customer to",
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"first_name","last_name","email","phone","band_size","quantity","plan"},
     *     @OA\Property(property="first_name",     type="string",  example="Jane"),
     *     @OA\Property(property="last_name",      type="string",  example="Daniel"),
     *     @OA\Property(property="email",          type="string",  format="email", example="jane@example.com"),
     *     @OA\Property(property="phone",          type="string",  example="+234 800 0000000"),
     *     @OA\Property(property="street_address", type="string",  nullable=true, example="12 Marina Road"),
     *     @OA\Property(property="city",           type="string",  nullable=true, example="Lagos"),
     *     @OA\Property(property="state",          type="string",  nullable=true, example="Lagos"),
     *     @OA\Property(property="band_size",      type="string",  enum={"S","M","L"}, example="M"),
     *     @OA\Property(property="quantity",       type="integer", minimum=1, maximum=10, example=1),
     *     @OA\Property(property="plan",           type="string",  enum={"band_only","band_care_plan"}, example="band_care_plan"))),
     *   @OA\Response(response=201, description="Order created — redirect customer to checkout_url",
     *     @OA\JsonContent(
     *       @OA\Property(property="success",      type="boolean", example=true),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="order_number", type="string",  example="STR-2026-00001"),
     *         @OA\Property(property="checkout_url", type="string",  example="https://checkout.korapay.com/..."),
     *         @OA\Property(property="total",        type="number",  example=149.00),
     *         @OA\Property(property="currency",     type="string",  example="USD")))),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=502, description="Korapay checkout initialization failed")) */
    public function placeOrder() {}

    /** @OA\Get(path="/api/v1/store/orders/{orderNumber}", tags={"Orders"},
     *   summary="Track an order by order number",
     *   @OA\Parameter(name="orderNumber", in="path", required=true,
     *     @OA\Schema(type="string", example="STR-2026-00001")),
     *   @OA\Response(response=200, description="Order status and tracking info",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="order_number",   type="string", example="STR-2026-00001"),
     *         @OA\Property(property="status",         type="string", enum={"pending","paid","processing","shipped","delivered","cancelled"}),
     *         @OA\Property(property="payment_status", type="string", enum={"pending","paid","failed"}),
     *         @OA\Property(property="band_size",      type="string", example="M"),
     *         @OA\Property(property="plan",           type="string", example="Band + Care Plan"),
     *         @OA\Property(property="total",          type="number", example=149.00),
     *         @OA\Property(property="shipping", type="object",
     *           @OA\Property(property="tracking_number", type="string",  nullable=true),
     *           @OA\Property(property="courier",         type="string",  nullable=true),
     *           @OA\Property(property="shipped_at",      type="string",  format="date-time", nullable=true),
     *           @OA\Property(property="estimated_days",  type="string",  example="5–8 business days")),
     *         @OA\Property(property="placed_at", type="string", format="date-time")))),
     *   @OA\Response(response=404, description="Order not found")) */
    public function trackOrder() {}

    /** @OA\Post(path="/api/v1/store/payment/webhook", tags={"Payment"},
     *   summary="Korapay payment webhook — marks order paid and sends confirmation email",
     *   description="Called automatically by Korapay. Signature verified via HMAC-SHA256 of the raw payload against KORAPAY_ENCRYPTION_KEY. Do not call manually.",
     *   @OA\Parameter(name="X-Korapay-Signature", in="header", required=true,
     *     @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Webhook processed"),
     *   @OA\Response(response=401, description="Invalid signature")) */
    public function webhook() {}

    /** @OA\Get(path="/api/v1/store/admin/orders", tags={"Admin"},
     *   summary="List all orders (paginated, filterable)",
     *   security={{"adminToken":{}}},
     *   @OA\Parameter(name="status", in="query", required=false,
     *     @OA\Schema(type="string", enum={"pending","paid","processing","shipped","delivered","cancelled"})),
     *   @OA\Parameter(name="search", in="query", required=false, description="Search by order number, email, or last name",
     *     @OA\Schema(type="string")),
     *   @OA\Parameter(name="per_page", in="query", required=false,
     *     @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="Paginated list of orders"),
     *   @OA\Response(response=401, description="Unauthorized")) */
    public function adminIndex() {}

    /** @OA\Get(path="/api/v1/store/admin/orders/{orderNumber}", tags={"Admin"},
     *   summary="Get full detail of a single order",
     *   security={{"adminToken":{}}},
     *   @OA\Parameter(name="orderNumber", in="path", required=true,
     *     @OA\Schema(type="string", example="STR-2026-00001")),
     *   @OA\Response(response=200, description="Order detail"),
     *   @OA\Response(response=404, description="Order not found"),
     *   @OA\Response(response=401, description="Unauthorized")) */
    public function adminShow() {}

    /** @OA\Patch(path="/api/v1/store/admin/orders/{orderNumber}/status", tags={"Admin"},
     *   summary="Update order status — setting 'shipped' requires tracking_number and courier, triggers shipping email",
     *   security={{"adminToken":{}}},
     *   @OA\Parameter(name="orderNumber", in="path", required=true,
     *     @OA\Schema(type="string", example="STR-2026-00001")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"status"},
     *     @OA\Property(property="status",          type="string", enum={"paid","processing","shipped","delivered","cancelled"}, example="shipped"),
     *     @OA\Property(property="tracking_number", type="string", nullable=true, example="1Z999AA10123456784",
     *       description="Required when status=shipped"),
     *     @OA\Property(property="courier",         type="string", nullable=true, example="DHL",
     *       description="Required when status=shipped"))),
     *   @OA\Response(response=200, description="Status updated"),
     *   @OA\Response(response=404, description="Order not found"),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=401, description="Unauthorized")) */
    public function adminUpdateStatus() {}
}
