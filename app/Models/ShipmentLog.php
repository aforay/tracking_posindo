<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ShipmentLog extends Model
{
    use HasFactory;

    protected $table = 'shipment_logs';

    protected $fillable = [
        'shipment_id',
        'user_id',
        'action',
        'note',
    ];

    /**
     * Relationship to the OutgoingShipment
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(OutgoingShipment::class, 'shipment_id');
    }

    /**
     * Relationship to the User who made the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Log a CS action on a shipment
     *
     * @param int|string $shipmentId
     * @param string $action
     * @param string|null $note
     * @param int|null $userId
     * @return static
     */
    public static function logAction(int|string $shipmentId, string $action, ?string $note = null, ?int $userId = null): self
    {
        return static::create([
            'shipment_id' => (int)$shipmentId,
            'user_id' => $userId ?: (Auth::id() ?? null),
            'action' => strtoupper(trim($action)),
            'note' => $note,
        ]);
    }
}
