<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PurchaseOrderPdfController extends Controller
{
    /**
     * Stream PDF PO ke browser.
     */
    public function cetak(PurchaseOrder $purchaseOrder): Response
    {
        $po = $purchaseOrder->load('items');

        $pdf = Pdf::loadView('pdf.purchase-order', ['po' => $po])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'Arial')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = 'PO-' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $po->nomor_po) . '.pdf';

        return $pdf->stream($filename);
    }
}
