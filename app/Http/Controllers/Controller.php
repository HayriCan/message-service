<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Message Service API",
 *     description="Automated message sending system with rate limiting"
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\Schema(
 *     schema="Message",
 *     type="object",
 *     title="Message",
 *     description="Sent message object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="phone_number", type="string", example="+905551234567"),
 *     @OA\Property(property="content", type="string", example="Hello World"),
 *     @OA\Property(property="message_id", type="string", example="abc-123-def"),
 *     @OA\Property(property="sent_at", type="string", format="date-time", example="2025-01-01T12:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     title="Pagination Meta",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=100),
 *     @OA\Property(property="last_page", type="integer", example=7)
 * )
 */
abstract class Controller
{
    //
}
