<?php

namespace App\Exceptions\Inventory;

use App\Exceptions\Consultation\ConsultationException;

/**
 * #295 — los rechazos del almacén.
 *
 * El consumo de materiales ocurre dentro del cierre de la atención
 * (`ConsultationService::close()`), cuyas dos puertas ya traducen
 * `ConsultationException` al sobre de la API (`error.error_code` +
 * `error.message`, 422). La guarda de stock comparte ese sobre en vez de
 * registrar un renderer propio: un insumo que no alcanza es un rechazo del acto
 * —con su código legible y su metadata— y no un fallo del servidor.
 */
class InventoryException extends ConsultationException {}
