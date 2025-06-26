<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreditNoteRequest;
use App\Models\AllowanceCharge;
use App\Models\BillingReference;
use App\Models\Company;
use App\Models\HealthField;
use App\Models\LegalMonetaryTotal;
use App\Models\Municipality;
use App\Models\OrderReference;
use App\Models\PaymentForm;
use App\Models\PaymentMethod;
use App\Models\TaxTotal;
use App\Models\TypeCurrency;
use App\Models\TypeDocument;
use App\Models\TypeOperation;
use App\Models\User;
use App\Models\Document;
use App\Models\InvoiceLine as CreditNoteLine;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Traits\DocumentTrait;
use ubl21dian\Templates\SOAP\SendTestSetAsync;
use ubl21dian\XAdES\SignCreditNote;

class CreditNoteController extends Controller
{
    use DocumentTrait;
    //
    public function testSetStore(CreditNoteRequest $request, $testSetId)
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
        $typeDocument = TypeDocument::findOrFail($request->type_document_id); //si es factura electronica de venta, si es factura de exportacion, si es factura de contigencia, nota credito etc.
        //si es documento equivalente $request->is_eqdoc = true
        if($request->is_eqdoc){
            $is_eqdoc = true;
            $pf = strtoupper($typeDocument->prefix);
            $pfs = strtoupper($typeDocument->prefix)."S";
        }
        else{
            $is_eqdoc = false;
            $pf = "NC";
            $pfs = "NCS";
        }
        
        //Customer
        $customerAll = collect($request->customer);
        if(isset($customerAll['municipality_id_fact']))
            $customerAll['municipality_id'] = Municipality::where('codefacturador', $customerAll['municipality_id_fact'])->first();
        $customer = new User($customerAll->toArray());
        
        // Customer company
        $customer->company = new Company($customerAll->toArray());
        
        // Type operation id
        if(!$request->type_operation_id)
          $request->type_operation_id = 12;
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

        // Order Reference
        if($request->order_reference)
            $orderreference = new OrderReference($request->order_reference);
        else
            $orderreference = NULL;

       // Health Fields
        if($request->health_fields)
            $healthfields = new HealthField($request->health_fields);
        else
            $healthfields = NULL;

        // Discrepancy response - MOTIVO DE LA NOTA
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
            if(isset($creditNoteLine['is_RNDC']) && $creditNoteLine['is_RNDC'] == TRUE)
                $request->isTransport = TRUE;
        }

        // Billing reference
        if(!$request->billing_reference)
            $billingReference = NULL;
        else{  //REFERENCIA A LA FACTURA ORIGINAL Incluye: (Número de factura original, Fecha, CUFE de la factura que se va a anular)
            $billingReference = new BillingReference($request->billing_reference);
            if($is_eqdoc){
                $billingReference->setSchemeNameAttribute("CUDE-SHA384");
                $billingReference->setDocumentTypeCodeAttribute(TypeDocument::where('id', $request->billing_reference['type_document_id'])->firstOrFail()->code);
            }
        }

        // Create XML
        if(isset($request->is_RNDC) && $request->is_RNDC == TRUE)
            $request->isTransport = TRUE;
        $crediNote = $this->createXML(compact('user', 'company', 'customer', 'taxTotals', 'withHoldingTaxTotal', 'resolution', 'paymentForm', 'typeDocument', 'creditNoteLines', 'allowanceCharges', 'legalMonetaryTotals', 'billingReference', 'date', 'time', 'notes', 'typeoperation', 'orderreference', 'discrepancycode', 'discrepancydescription', 'request', 'idcurrency', 'calculationrate', 'calculationratedate', 'healthfields'));
        //$crediNote->saveXML();
        
        // Signature XML
        $signCreditNote = new SignCreditNote($company->certificate->path, $company->certificate->password);
        if($is_eqdoc){
            $signCreditNote->softwareID = $company->software->identifier_eqdocs;
            $signCreditNote->pin = $company->software->pin_eqdocs;
        }
        else{
            $signCreditNote->softwareID = $company->software->identifier;
            $signCreditNote->pin = $company->software->pin;
        }
         
        //Crear direccion para guardar el archivo xml
        if ($request->GuardarEn){
            if (!is_dir($request->GuardarEn)) {
                mkdir($request->GuardarEn);
            }
            $signCreditNote->GuardarEn = $request->GuardarEn."\\FE-{$resolution->next_consecutive}.xml";
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
            $signCreditNote->GuardarEn = storage_path("app/public/{$company->identification_number}/FE-{$resolution->next_consecutive}.xml");  //direccion local para guardar el archivo xml signInvoice->GuardarEn = app/public/1094955142/FE-SETUP994411000.xml
        }

        /*
        $sendTestSetAsync = new SendTestSetAsync($company->certificate->path, $company->certificate->password);
        if($is_eqdoc) //si es documento equivalente
            $sendTestSetAsync->To = $company->software->url_eqdocs;
        else
            $sendTestSetAsync->To = $company->software->url;

        $sendTestSetAsync->fileName = "{$resolution->next_consecutive}.xml";
        
        if ($request->GuardarEn){
            $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signCreditNote->sign($crediNote), $request->GuardarEn."\\{$pfs}-{$resolution->next_consecutive}");
        }else{
            $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signCreditNote->sign($crediNote), storage_path("app/public/{$company->identification_number}/{$pfs}-{$resolution->next_consecutive}"));
        }
        $sendTestSetAsync->testSetId = $testSetId;
        */

        //$QRStr = $this->createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signCreditNote->ConsultarCUDE(), "NC", $withHoldingTaxTotal, $notes, $healthfields);

        return [
            'mensaje'=>'Nota credito realizada con exito',
        ];
    }
}
