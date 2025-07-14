<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupportDocumentRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\TaxTotal;
use App\Models\InvoiceLine;
use App\Models\PaymentForm;
use App\Models\TypeDocument;
use App\Models\TypeContract;
use App\Models\TypeOperation;
use App\Models\TypeCurrency;
use App\Models\PaymentMethod;
use App\Models\AllowanceCharge;
use App\Models\LegalMonetaryTotal;
use App\Models\PrepaidPayment;
use App\Models\Municipality;
use App\Models\OrderReference;
use App\Models\HealthField;
//use App\Models\Health;
use App\Models\Document;
use Illuminate\Http\Request;
use App\Traits\DocumentTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Storage;
use stdClass;
use ubl21dian\XAdES\SignInvoice;
use ubl21dian\XAdES\SignAttachedDocument;
use ubl21dian\Templates\SOAP\SendBillSync;
use ubl21dian\Templates\SOAP\SendTestSetAsync;


class SupportDocumentController extends Controller
{
    //
    public function store(Request $request)
    {

    }


    public function testSetStore(SupportDocumentRequest $request, $testSetId)
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

        // Seller
        $sellerAll = collect($request->seller);
        if(isset($sellerAll['municipality_id_fact']))
            $sellerAll['municipality_id'] = Municipality::where('codefacturador', $sellerAll['municipality_id_fact'])->first();
        $seller = new User($sellerAll->toArray());

        // Seller company
        $seller->company = new Company($sellerAll->toArray());
        $seller->postal_zone_code = $sellerAll['postal_zone_code'];

        // Delivery
        if($request->delivery){
            $deliveryAll = collect($request->delivery);
            $delivery = new User($deliveryAll->toArray());

            // Delivery company
            $delivery->company = new Company($deliveryAll->toArray());

            // Delivery party
            $deliverypartyAll = collect($request->deliveryparty);
            $deliveryparty = new User($deliverypartyAll->toArray());

            // Delivery party company
            $deliveryparty->company = new Company($deliverypartyAll->toArray());
        }else{
            $delivery = NULL;
            $deliveryparty = NULL;
        }

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
        $request->resolution->number = $request->number;
        $resolution = $request->resolution;

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

        // Payment form default
        $paymentFormAll = (object) array_merge($this->paymentFormDefault, $request->payment_form ?? []);
        $paymentForm = PaymentForm::findOrFail($paymentFormAll->payment_form_id);
        $paymentForm->payment_method_code = PaymentMethod::findOrFail($paymentFormAll->payment_method_id)->code;
        $paymentForm->nameMethod = PaymentMethod::findOrFail($paymentFormAll->payment_method_id)->name;
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

         // Prepaid Payment
        if($request->prepaid_payment)
            $prepaidpayment = new PrepaidPayment($request->prepaid_payment);
        else
            $prepaidpayment = NULL;

        // Legal monetary totals
        $legalMonetaryTotals = new LegalMonetaryTotal($request->legal_monetary_totals);

        // Invoice lines
        $invoiceLines = collect();
        foreach ($request->invoice_lines as $invoiceLine) {
            $invoiceLines->push(new InvoiceLine($invoiceLine));
        }

        // Create XML
        $invoice = $this->createXML(compact('user', 'company', 'seller', 'taxTotals', 'withHoldingTaxTotal', 'resolution', 'paymentForm', 'typeDocument', 'invoiceLines', 'allowanceCharges', 'legalMonetaryTotals', 'date', 'time', 'notes', 'typeoperation', 'orderreference', 'prepaidpayment', 'delivery', 'deliveryparty', 'request', 'idcurrency', 'calculationrate', 'calculationratedate', 'healthfields'));
        //echo htmlentities($invoice->saveXML());
        //json_encode($invoice);

        // Register Seller
        //$this->registerCustomer($seller, $request->sendmail);  //registrar en la tabla customer

        // Signature XML firma del documento
        $signInvoice = new SignInvoice($company->certificate->path, $company->certificate->password);
        $signInvoice->softwareID = $company->software->identifier;
        $signInvoice->pin = $company->software->pin;
        $signInvoice->technicalKey = $resolution->technical_key;

        //Crear direccion para guardar el archivo xml
        if ($request->GuardarEn){
            if (!is_dir($request->GuardarEn)) {
                mkdir($request->GuardarEn);
            }
            $signInvoice->GuardarEn = $request->GuardarEn."\\DS-{$resolution->next_consecutive}.xml";
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
            $signInvoice->GuardarEn = storage_path("app/public/{$company->identification_number}/DS-{$resolution->next_consecutive}.xml");  //direccion local para guardar el archivo xml signInvoice->GuardarEn = app/public/1094955142/FE-SETUP994411000.xml
        }

        // enviar documento
        $sendTestSetAsync = new SendTestSetAsync($company->certificate->path, $company->certificate->password);
        $sendTestSetAsync->To = $company->software->url;
        $sendTestSetAsync->fileName = "{$resolution->next_consecutive}.xml";

        if ($request->GuardarEn){                                                   // firma del envio
          $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signInvoice->sign($invoice), $request->GuardarEn."\\DSS-{$resolution->next_consecutive}");
        }else{                                                              // se enia documento firmado y el camino en donde se va aguardar el documento firmado
          $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signInvoice->sign($invoice), storage_path("app/public/{$company->identification_number}/DSS-{$resolution->next_consecutive}"));
        }
        $sendTestSetAsync->testSetId = $testSetId;

        $QRStr = $this->createPDF($user, $company, $seller, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signInvoice->ConsultarCUDS(), "SUPPORTDOCUMENT", $withHoldingTaxTotal, $notes, $healthfields);

        return [
                'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
                'ResponseDian' => $sendTestSetAsync->signToSend(storage_path("app/public/{$company->identification_number}/ReqDS-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaDS-{$resolution->next_consecutive}.xml")), //enviar documento firmado y obtener su respuesta.
                'invoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/DSS-{$resolution->next_consecutive}.xml"))),
                'zipinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/DSS-{$resolution->next_consecutive}.zip"))),
                'unsignedinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/DS-{$resolution->next_consecutive}.xml"))),
                'reqfe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqDS-{$resolution->next_consecutive}.xml"))),
                'rptafe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaDS-{$resolution->next_consecutive}.xml"))),
                'urlinvoicexml'=>"DSS-{$resolution->next_consecutive}.xml",
                'urlinvoicepdf'=>"DSS-{$resolution->next_consecutive}.pdf",
                'urlinvoiceattached'=>"Attachment-{$resolution->next_consecutive}.xml",
                'cuds' => $signInvoice->ConsultarCUDS(),
                'QRStr' => $QRStr,
                'certificate_days_left' => $certificate_days_left,
                'resolution_days_left' => $this->days_between_dates(Carbon::now()->format('Y-m-d'), $resolution->date_to),
            ];

    }
}
