<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\View\View;

/**
 * Read-only weergave van een factuur met de gebundelde posten (§23).
 */
class InvoiceController extends Controller
{
    public function show(Invoice $invoice): View
    {
        $invoice->load(['debtor', 'charges.product']);

        return view('admin.invoices.show', [
            'invoice' => $invoice,
        ]);
    }
}
