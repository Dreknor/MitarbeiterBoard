<div class="row mt-2" id="card_{{$card->id}}">
    <div class="col-12">
        <div class="card shadow-sm border-0 free-rooms-card">
            <div class="card-header text-white bg-gradient-directional-blue free-rooms-header">
                <h5 class="card-title mb-0 free-rooms-title">
                    <i class="ft-home"></i>
                    <span>Freie Räume</span>
                </h5>
                <a href="#" class="text-white btn btn-link" onclick="disableCard('{{$card->id}}')" title="Karte schließen">
                    X
                </a>
            </div>
            <div class="card-body p-3">
                @if($freeRooms and $freeRooms->count() > 0)
                    <div class="free-rooms-grid">
                        @foreach($freeRooms as $room)
                            <div class="room-card">
                                <div class="room-card-header">
                                    <h6 class="room-name">{{$room->name}}</h6>
                                </div>

                                @if($room->nextBooking())
                                    <div class="room-status room-status-warning">
                                        <i class="ft-clock"></i>
                                        <span class="room-status-text">{{Carbon\Carbon::parse($room->nextBooking()->start)->diffForHumans()}} belegt</span>
                                    </div>
                                @else
                                    <div class="room-status room-status-success">
                                        <i class="ft-check-circle"></i>
                                        <span class="room-status-text">Keine Buchungen</span>
                                    </div>
                                @endif

                                <a href="{{url('rooms/rooms/'.$room->id)}}" class="room-btn">
                                    <i class="ft-eye"></i>
                                    <span>Details</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">
                        <i class="ft-alert-circle"></i>
                        <p>Es stehen derzeit keine freien Räume zur Verfügung</p>
                    </div>
                @endif
            </div>
            <div class="card-footer bg-white border-top-0 pt-0 pb-3 px-3">
                <a href="{{url('rooms/rooms')}}" class="btn btn-block btn-bg-gradient-x-blue-green shadow-sm all-rooms-btn">
                    <i class="ft-list"></i>
                    <span>Alle Räume anzeigen</span>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Basis Styling für die Card */
    .free-rooms-card {
        overflow: hidden;
    }

    /* Header */
    .free-rooms-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        gap: 0.75rem;
    }

    .free-rooms-title {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 1rem;
        font-weight: 600;
        margin: 0;
        min-width: 0;
    }

    .free-rooms-title span {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .close-btn {
        flex-shrink: 0;
        width: 1.75rem;
        height: 1.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.8;
        transition: opacity 0.2s;
    }

    .close-btn:hover {
        opacity: 1;
    }

    /* Grid Container - Responsive mit CSS Grid */
    .free-rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 0.75rem;
        width: 100%;
    }

    /* Einzelne Raum-Karte - KOMPAKTER */
    .room-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 0.375rem;
        padding: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        min-width: 0;
    }

    .room-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
    }

    /* Raum-Header mit Icon und Name - KOMPAKTER */
    .room-card-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .room-icon {
        flex-shrink: 0;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.875rem;
    }

    .room-name {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 600;
        color: #2c3e50;
        line-height: 1.25;
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        min-width: 0;
        flex: 1;
    }

    /* Status Badge - KOMPAKTER */
    .room-status {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.625rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        line-height: 1.3;
        min-width: 0;
    }

    .room-status i {
        flex-shrink: 0;
        font-size: 0.75rem;
    }

    .room-status-text {
        word-wrap: break-word;
        overflow-wrap: break-word;
        min-width: 0;
        flex: 1;
    }

    .room-status-warning {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
    }

    .room-status-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    /* Button - KOMPAKTER */
    .room-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        padding: 0.5rem 0.75rem;
        background: #fff;
        color: #007bff;
        border: 1px solid #007bff;
        border-radius: 0.25rem;
        text-decoration: none;
        font-size: 0.8125rem;
        font-weight: 500;
        transition: all 0.2s ease;
        text-align: center;
        min-width: 0;
    }

    .room-btn i {
        flex-shrink: 0;
        font-size: 0.875rem;
    }

    .room-btn span {
        white-space: nowrap;
    }

    .room-btn:hover {
        background: #007bff;
        color: #fff;
        text-decoration: none;
        transform: translateY(-1px);
    }

    /* Empty State */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        text-align: center;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
        opacity: 0.5;
    }

    .empty-state p {
        margin: 0;
        font-size: 0.875rem;
        max-width: 400px;
    }

    /* Footer Button */
    .all-rooms-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-weight: 500;
        font-size: 0.9375rem;
    }

    .all-rooms-btn i {
        flex-shrink: 0;
    }

    /* Responsive Anpassungen */

    /* Sehr kleine Geräte (< 360px) */
    @media (max-width: 359px) {
        .free-rooms-grid {
            grid-template-columns: 1fr;
            gap: 0.625rem;
        }

        .room-card {
            padding: 0.625rem;
        }

        .free-rooms-title {
            font-size: 0.9375rem;
        }

        .room-name {
            font-size: 0.875rem;
        }

        .room-btn span {
            font-size: 0.75rem;
        }
    }

    /* Kleine Mobile Geräte (360px - 575px) */
    @media (min-width: 360px) and (max-width: 575px) {
        .free-rooms-grid {
            grid-template-columns: 1fr;
            gap: 0.625rem;
        }
    }

    /* Mobile Landscape / Tablets (576px - 767px) */
    @media (min-width: 576px) and (max-width: 767px) {
        .free-rooms-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
        }
    }

    /* Tablets (768px - 991px) */
    @media (min-width: 768px) and (max-width: 991px) {
        .free-rooms-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        }
    }

    /* Desktop (992px - 1199px) */
    @media (min-width: 992px) and (max-width: 1199px) {
        .free-rooms-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        }
    }

    /* Large Desktop (>= 1200px) */
    @media (min-width: 1200px) {
        .free-rooms-grid {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        }
    }

    /* Extra Large Desktop (>= 1400px) */
    @media (min-width: 1400px) {
        .free-rooms-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }
    }

    /* Print Styles */
    @media print {
        .close-btn,
        .all-rooms-btn {
            display: none;
        }

        .room-card {
            break-inside: avoid;
        }
    }
</style>

