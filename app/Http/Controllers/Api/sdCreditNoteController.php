<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\sdCreditNoteRequest;
use Illuminate\Http\Request;

class sdCreditNoteController extends Controller
{
    //
    public function store(sdCreditNoteRequest $request)
    {
        //obtener usuario
        $user = auth()->user();
        //obtener compañia
        $company = $user->company;
        //Verifica certificado
        $certificate_days_left = 0;
        $c = $this->verify_certificate();
        if(!$c['success']){
            return $c;
        }else{
            $certificate_days_left = $c['certificate_days_left'];
        }

        // Type document
        $typeDocument = TypeDocument::findOrFail($request->type_document_id); //si es factura electronica de venta, si es factura de exportacion, si es factura de contigencia, nota credito de documento etc.
        
        // Seller
        $sellerAll = collect($request->seller);
        if(isset($sellerAll['municipality_id_fact']))
            $sellerAll['municipality_id'] = Municipality::where('codefacturador', $sellerAll['municipality_id_fact'])->first();
        $seller = new User($sellerAll->toArray());

        // Seller company
        $seller->company = new Company($sellerAll->toArray());
        $seller->postal_zone_code = $sellerAll['postal_zone_code'];

        // Type operation id
        if(!$request->type_operation_id)
            $request->type_operation_id = 10;
        $typeoperation = TypeOperation::findOrFail($request->type_operation_id);

        // Currency id
        if(isset($request->idcurrency) and (!is_null($request->idcurrency))){
            $idcurrency = TypeCurrency::findOrFail($request->idcurrency);
            $calculationrate = $request->calculationrate;
            $calculationratedate = $request->calculationratedate;
        }
        else{
            $idcurrency = TypeCurrency::findOrFail(35/*$invoice_doc->currency_id*/);
            $calculationrate = 1;
            $calculationratedate = Carbon::now()->format('Y-m-d');
        }

        // Resolution
        $request->resolution->number = $request->number;  //establece valor en el campo virtual number por medio de setNumberAttribute, por el model Resolution.
        $resolution = $request->resolution;
    
        //validar documento antes de enviar
        /*
        if(env('VALIDATE_BEFORE_SENDING', false)){
            $doc = Document::where('type_document_id', $request->type_document_id)->where('identification_number', $company->identification_number)->where('prefix', $resolution->prefix)->where('number', $request->number)->where('state_document_id', 1)->get();
            if(count($doc) > 0)
                return [
                    'success' => false,
                    'message' => 'Este documento ya fue enviado anteriormente, se registra en la base de datos.',
                    'customer' => $doc[0]->customer,
                    'cufe' => $doc[0]->cufe,
                    'sale' => $doc[0]->total,
                ];
        }*/

        // Date time
        $date = $request->date;
        $time = $request->time;
        
        // Notes
        $notes = $request->notes;

        // Discrepancy response
        $discrepancycode = $request->discrepancyresponsecode;
        $discrepancydescription = $request->discrepancyresponsedescription;

        // Payment form default
        $paymentFormAll = (object) array_merge($this->paymentFormDefault, $request->payment_form ?? []);
        $paymentForm = PaymentForm::findOrFail($paymentFormAll->payment_form_id);
        $paymentForm->payment_method_code = PaymentMethod::findOrFail($paymentFormAll->payment_method_id)->code;
        $paymentForm->payment_due_date = $paymentFormAll->payment_due_date ?? null;
        $paymentForm->duration_measure = $paymentFormAll->duration_measure ?? null;

        // Allowance charges
        $allowanceCharges = collect();
        foreach ($request->allowance_charges ?? [] as $allowanceCharge) {
            $allowanceCharges->push(new AllowanceCharge($allowanceCharge));
        }

        // Tax totals
        $taxTotals = collect();
        foreach ($request->tax_totals ?? [] as $taxTotal) {
            $taxTotals->push(new TaxTotal($taxTotal));
        }

        // Retenciones globales
        $withHoldingTaxTotal = collect();
        //$withHoldingTaxTotalCount = 0;
        //$holdingTaxTotal = $request->holding_tax_total;
        foreach($request->with_holding_tax_total ?? [] as $item) {
            //$withHoldingTaxTotalCount++;
            //$holdingTaxTotal = $request->holding_tax_total;
            $withHoldingTaxTotal->push(new TaxTotal($item));
        }

        // Legal monetary totals
        $legalMonetaryTotals = new LegalMonetaryTotal($request->legal_monetary_totals);

        // Credit note lines
        $creditNoteLines = collect();
        foreach ($request->credit_note_lines as $creditNoteLine) {
            $creditNoteLines->push(new CreditNoteLine($creditNoteLine));
        }

        // Billing reference
        $billingReference = new BillingReference($request->billing_reference);

        // Create XML
        $crediNote = $this->createXML(compact('user', 'company', 'seller', 'taxTotals', 'withHoldingTaxTotal', 'resolution', 'paymentForm', 'typeDocument', 'creditNoteLines', 'allowanceCharges', 'legalMonetaryTotals', 'billingReference', 'date', 'time', 'notes', 'typeoperation', 'discrepancycode', 'discrepancydescription', 'request', 'idcurrency', 'calculationrate', 'calculationratedate'));
        //$crediNote->saveXML();

        // Signature XML
        $signCreditNote = new SignCreditNote($company->certificate->path, $company->certificate->password);
        $signCreditNote->softwareID = $company->software->identifier;
        $signCreditNote->pin = $company->software->pin;

        //Crear direccion para guardar el archivo xml
        if ($request->GuardarEn){
            if (!is_dir($request->GuardarEn)) {
                mkdir($request->GuardarEn);
            }
            $signCreditNote->GuardarEn = $request->GuardarEn."\\NDSN-{$resolution->next_consecutive}.xml"; //obtiene el valor del campo virtual next_consecutive por medio de getNextConsecutiveAttribute.
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
            $signCreditNote->GuardarEn = storage_path("app/public/{$company->identification_number}/NDSN-{$resolution->next_consecutive}.xml");  //direccion local para guardar el archivo xml signInvoice->GuardarEn = app/public/1094955142/FE-SETUP994411000.xml
        }

        $sendBillSync = new SendBillSync($company->certificate->path, $company->certificate->password);
        $sendBillSync->To = $company->software->url;
        $sendBillSync->fileName = "{$resolution->next_consecutive}.xml";

    }

    public function testSetStore(sdCreditNoteRequest $request, $testSetId)
    {
        //obtener usuario
        $user = auth()->user();
        //obtener compañia
        $company = $user->company;
        //Verifica certificado
        $certificate_days_left = 0;
        $c = $this->verify_certificate();
        if(!$c['success']){
            return $c;
        }else{
            $certificate_days_left = $c['certificate_days_left'];
        }

        // Type document
        $typeDocument = TypeDocument::findOrFail($request->type_document_id); //si es factura electronica de venta, si es factura de exportacion, si es documento soporte, nota credito etc.

        // Seller
        $sellerAll = collect($request->seller);
        if(isset($sellerAll['municipality_id_fact']))
            $sellerAll['municipality_id'] = Municipality::where('codefacturador', $sellerAll['municipality_id_fact'])->first();
        $seller = new User($sellerAll->toArray());

        // Seller company
        $seller->company = new Company($sellerAll->toArray());
        $seller->postal_zone_code = $sellerAll['postal_zone_code'];

        // Type operation id
        if(!$request->type_operation_id)
          $request->type_operation_id = 10;  //tipo de operacion residente
        $typeoperation = TypeOperation::findOrFail($request->type_operation_id);

        // Currency id
        if(isset($request->idcurrency) and (!is_null($request->idcurrency))){
            $idcurrency = TypeCurrency::findOrFail($request->idcurrency);
            $calculationrate = $request->calculationrate;
            $calculationratedate = $request->calculationratedate;
        }
        else{
            $idcurrency = TypeCurrency::findOrFail(35/*$invoice_doc->currency_id*/);
            $calculationrate = 1;
            $calculationratedate = Carbon::now()->format('Y-m-d');
        }

        // Resolution
        $request->resolution->number = $request->number;
        $resolution = $request->resolution;

        // Date time
        $date = $request->date;
        $time = $request->time;

        // Notes
        $notes = $request->notes;

        // Discrepancy response
        $discrepancycode = $request->discrepancyresponsecode;
        $discrepancydescription = $request->discrepancyresponsedescription;

        // Payment form default
        $paymentFormAll = (object) array_merge($this->paymentFormDefault, $request->payment_form ?? []);
        $paymentForm = PaymentForm::findOrFail($paymentFormAll->payment_form_id);
        $paymentForm->payment_method_code = PaymentMethod::findOrFail($paymentFormAll->payment_method_id)->code;
        $paymentForm->payment_due_date = $paymentFormAll->payment_due_date ?? null;
        $paymentForm->duration_measure = $paymentFormAll->duration_measure ?? null;

        // Allowance charges
        $allowanceCharges = collect();
        foreach ($request->allowance_charges ?? [] as $allowanceCharge) {
            $allowanceCharges->push(new AllowanceCharge($allowanceCharge));
        }

        // Tax totals
        $taxTotals = collect();
        foreach ($request->tax_totals ?? [] as $taxTotal) {
            $taxTotals->push(new TaxTotal($taxTotal));
        }

        // Retenciones globales
        $withHoldingTaxTotal = collect();
        //$withHoldingTaxTotalCount = 0;
        //$holdingTaxTotal = $request->holding_tax_total;
        foreach($request->with_holding_tax_total ?? [] as $item) {
            //$withHoldingTaxTotalCount++;
            //$holdingTaxTotal = $request->holding_tax_total;
            $withHoldingTaxTotal->push(new TaxTotal($item));
        }

        // Legal monetary totals
        $legalMonetaryTotals = new LegalMonetaryTotal($request->legal_monetary_totals);

        // Credit note lines
        $creditNoteLines = collect();
        foreach ($request->credit_note_lines as $creditNoteLine) {
            $creditNoteLines->push(new CreditNoteLine($creditNoteLine));
        }

        // Billing reference
        $billingReference = new BillingReference($request->billing_reference);

        // Create XML
        $crediNote = $this->createXML(compact('user', 'company', 'seller', 'taxTotals', 'withHoldingTaxTotal', 'resolution', 'paymentForm', 'typeDocument', 'creditNoteLines', 'allowanceCharges', 'legalMonetaryTotals', 'billingReference', 'date', 'time', 'notes', 'typeoperation', 'discrepancycode', 'discrepancydescription', 'request', 'idcurrency', 'calculationrate', 'calculationratedate'));
        //$crediNote->saveXML();

        // Signature XML
        $signCreditNote = new SignCreditNote($company->certificate->path, $company->certificate->password);
        $signCreditNote->softwareID = $company->software->identifier;
        $signCreditNote->pin = $company->software->pin;

        //Crear direccion para guardar el archivo xml
        if ($request->GuardarEn){
            if (!is_dir($request->GuardarEn)) {
                mkdir($request->GuardarEn);
            }
            $signCreditNote->GuardarEn = $request->GuardarEn."\\NDSN-{$resolution->next_consecutive}.xml"; //obtiene el valor del campo virtual next_consecutive por medio de getNextConsecutiveAttribute.
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
            $signCreditNote->GuardarEn = storage_path("app/public/{$company->identification_number}/NDSN-{$resolution->next_consecutive}.xml");  //direccion local para guardar el archivo xml signInvoice->GuardarEn = app/public/1094955142/FE-SETUP994411000.xml
        }

        $sendTestSetAsync = new SendTestSetAsync($company->certificate->path, $company->certificate->password);
        $sendTestSetAsync->To = $company->software->url;
        $sendTestSetAsync->fileName = "{$resolution->next_consecutive}.xml";

        if ($request->GuardarEn){
            $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signCreditNote->sign($crediNote), $request->GuardarEn."\\NDSNS-{$resolution->next_consecutive}");
        }else{
            $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signCreditNote->sign($crediNote), storage_path("app/public/{$company->identification_number}/NDSNS-{$resolution->next_consecutive}"));
        }
        $sendTestSetAsync->testSetId = $testSetId;
        

        $QRStr = $this->createPDF($user, $company, $seller, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signCreditNote->ConsultarCUDS(), "SUPPORTDOCUMENTNOTE", $withHoldingTaxTotal, $notes, NULL);

        return [
            'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
            'ResponseDian' => $sendTestSetAsync->signToSend(storage_path("app/public/{$company->identification_number}/ReqNDSN-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaNDSN-{$resolution->next_consecutive}.xml")),
            //'invoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NDSNS-{$resolution->next_consecutive}.xml"))),
            //'zipinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NDSNS-{$resolution->next_consecutive}.zip"))),
            //'unsignedinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NDSN-{$resolution->next_consecutive}.xml"))),
            //'reqfe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqNDSN-{$resolution->next_consecutive}.xml"))),
            //'rptafe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaNDSN-{$resolution->next_consecutive}.xml"))),
            'urlinvoicexml'=>"NDSNS-{$resolution->next_consecutive}.xml",
            'urlinvoicepdf'=>"NDSNS-{$resolution->next_consecutive}.pdf",
            'urlinvoiceattached'=>"Attachment-{$resolution->next_consecutive}.xml",
            'cuds' => $signCreditNote->ConsultarCUDS(),
            'QRStr' => $QRStr,
            'certificate_days_left' => $certificate_days_left,
        ];

    }
}
