<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InvoiceRequest;
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


class InvoiceController extends Controller
{
    use DocumentTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return "hola index";
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InvoiceRequest $request)
    {
        //
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

        //Customer
        $customerAll = collect($request->customer);
        if(isset($customerAll['municipality_id_fact']))
            $customerAll['municipality_id'] = Municipality::where('codefacturador', $customerAll['municipality_id_fact'])->first();
        $customer = new User($customerAll->toArray());
        
        // Customer company
        $customer->company = new Company($customerAll->toArray());

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
        $request->resolution->number = $request->number; //establece en el campo virtual number por medio de setNumberAttribute el valor, por el model Resolution.
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
        $invoice = $this->createXML(compact('user', 'company', 'customer', 'taxTotals', 'withHoldingTaxTotal', 'resolution', 'paymentForm', 'typeDocument', 'invoiceLines', 'allowanceCharges', 'legalMonetaryTotals', 'date', 'time', 'notes', 'typeoperation', 'orderreference', 'prepaidpayment', 'delivery', 'deliveryparty', 'request', 'idcurrency', 'calculationrate', 'calculationratedate', 'healthfields'));

        // Signature XML
        $signInvoice = new SignInvoice($company->certificate->path, $company->certificate->password);
        $signInvoice->softwareID = $company->software->identifier;
        $signInvoice->pin = $company->software->pin;
        $signInvoice->technicalKey = $resolution->technical_key;

        //Crear direccion para guardar el archivo xml
        if ($request->GuardarEn){
            if (!is_dir($request->GuardarEn)) {
                mkdir($request->GuardarEn);
            }
            $signInvoice->GuardarEn = $request->GuardarEn."\\FE-{$resolution->next_consecutive}.xml"; //obtiene el valor del campo virtual next_consecutive por medio de getNextConsecutiveAttribute
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
            $signInvoice->GuardarEn = storage_path("app/public/{$company->identification_number}/FE-{$resolution->next_consecutive}.xml");  //direccion local para guardar el archivo xml signInvoice->GuardarEn = app/public/1094955142/FE-SETUP994411000.xml
        }

        $sendBillSync = new SendBillSync($company->certificate->path, $company->certificate->password);
        $sendBillSync->To = $company->software->url;
        $sendBillSync->fileName = "{$resolution->next_consecutive}.xml";
        if ($request->GuardarEn){
            $sendBillSync->contentFile = $this->zipBase64($company, $resolution, $signInvoice->sign($invoice), $request->GuardarEn."\\FES-{$resolution->next_consecutive}");
        }else{ $sendBillSync->contentFile = $this->zipBase64($company, $resolution, $signInvoice->sign($invoice), storage_path("app/public/{$company->identification_number}/FES-{$resolution->next_consecutive}")); }

        $QRStr = $this->createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signInvoice->ConsultarCUFE(), "INVOICE", $withHoldingTaxTotal, $notes, $healthfields);

        $filename = '';
        $respuestadian = '';
        $typeDocument = TypeDocument::findOrFail(7); //tipo de documento es AttachedDocument code = 89, prefix = at

        $ar = new \DOMDocument;


        ///////////////////// validar y probar envio de solo email ////////////////////////
        /*$objtype_document = new stdClass();
        $objtype_document->code = 6;
        $objtypedocument = new stdClass();
        $objtypedocument->code = 1;
        $objtypedocument->prefix = 'SETP';
        $objtypedocument->number = 994411022;
        $objtypedocument->pdf = "FES-$resolution->next_consecutive.pdf";
        $objtypedocument->total = 33000;
        $objtypedocument->created_at = $date;
        $objtypedocument->type_document = $objtype_document;
        $filename = "FES-".$resolution->next_consecutive;
        $invoice = [];
        $invoice[0] = $objtypedocument;
        Mail::to($customer->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, TRUE, $request));*/
        ////////////////////////////////////////////////////


        
        try{
            $respuestadian = $sendBillSync->signToSend(storage_path("app/public/{$company->identification_number}/ReqFE-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaFE-{$resolution->next_consecutive}.xml"));
            if(isset($respuestadian->html)){
                return ['success' => false, 'message' => "El servicio de la DIAN no se encuentra disponible, intente mas tarde."];
            }
            
            if($respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == 'true'){
                //nombre del attacheddocument colocando 'ad'
                $filename = str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlFileName)));
                if($request->atacheddocument_name_prefix)  //si se pasa el nombre para el attacheddocument
                    $filename = $request->atacheddocument_name_prefix.$filename;
                $cufecude = $respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlDocumentKey;
                //$invoice_doc->state_document_id = 1;
                //$invoice_doc->cufe = $cufecude;
                //$invoice_doc->save();

                //Obtener XML firmado, partiendo del nombre de la respuesta de la Dian
                $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlFileName.".xml"));
                if(strpos($signedxml, "</Invoice>") > 0) //busca el string "</Invoice>" en la variable $signedxml y devuelve la posicion de lo contrario devuelve false.
                    $td = '/Invoice';
                else
                    if(strpos($signedxml, "</CreditNote>") > 0)
                        $td = '/CreditNote';
                    else
                        $td = '/DebitNote';
                //Obtener XML en decodificado, partiendo de la respuesta de la Dian en base 64
                $appresponsexml = base64_decode($respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlBase64Bytes);
                $ar->loadXML($appresponsexml); //cargar el xml decodificado en libreria DOMDocument
                $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/"); // => ($signedxml, "/Invoice/cbc:ID/") ,  valueXML retorma: 12345 de <Invoice><cbc:ID>12345</cbc:ID></Invoice>

                // CreateXML AttachedDocument, es diferente al createXML de la factura que se envia a la Dian.
                $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));

                // Signature XML
                $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                $signAttachedDocument->GuardarEn = storage_path("app/public/{$company->identification_number}/{$filename}.xml");
                
                $at = $signAttachedDocument->sign($attacheddocument)->xml; //firma del attacheddocument
                $file = fopen(storage_path("app/public/{$company->identification_number}/{$filename}".".xml"), "w"); //El archivo es abierto para escritura, si el archivo no existe, lo crea. si ya existe el archivo, su contenido se borra para sobreescribir
                fwrite($file, $at);  //escribirá desde cero, empezando con un archivo vacío.
                fclose($file); //cerrar archivo para liberar recursos
                if(isset($request->sendmail)){ //Valida que el email del cliente este presente
                    if($request->sendmail){
                        if($customer->company->identification_number != '222222222222'){
                            try{
                                //Enviar email de la factura al cliente consumidor
                                
                                Mail::to($customer->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, TRUE, $request));
                                //enviar email de la factura a mi o negocio
                                if($request->sendmailtome)
                                    Mail::to($user->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE, $request));
                            } catch (\Exception $m) {
                                \Log::debug($m->getMessage());
                            }
                        }
                    }
                }
                
            }else{
                $invoice = null;
                $at = '';
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        return [
                'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
                'filename' => $filename,
                //'invoice' => $invoice,
                //'pathXML' => storage_path("app/public/{$company->identification_number}/{$filename}.xml"),
                //'pathPDF' => storage_path("app/public/{$company->identification_number}/{$invoice[0]->pdf}")
                'send_email_success' => (null !== $invoice && $request->sendmail == true) ?? $invoice[0]->send_email_success == 1,
                'send_email_date_time' => (null !== $invoice && $request->sendmail == true) ?? Carbon::now()->format('Y-m-d H:i'),
                'ResponseDian' => $respuestadian,
                //'invoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/FES-{$resolution->next_consecutive}.xml"))),
                //'zipinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/FES-{$resolution->next_consecutive}.zip"))),
                //'unsignedinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/FE-{$resolution->next_consecutive}.xml"))),
                //'reqfe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqFE-{$resolution->next_consecutive}.xml"))),
                //'rptafe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaFE-{$resolution->next_consecutive}.xml"))),
                //'attacheddocument'=>base64_encode($at),
                'urlinvoicexml'=>"FES-{$resolution->next_consecutive}.xml",
                'urlinvoicepdf'=>"FES-{$resolution->next_consecutive}.pdf",
                'urlinvoiceattached'=>"{$filename}.xml",
                'cufe' => $signInvoice->ConsultarCUFE(),
                'QRStr' => $QRStr,
                'certificate_days_left' => $certificate_days_left,
                'resolution_days_left' => $this->days_between_dates(Carbon::now()->format('Y-m-d'), $resolution->date_to),
            ];

    }



    /**
     * Display the specified resource.
     */
    public function testSetStore(InvoiceRequest $request, $testSetId)
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

        //Customer
        $customerAll = collect($request->customer);
        if(isset($customerAll['municipality_id_fact']))
            $customerAll['municipality_id'] = Municipality::where('codefacturador', $customerAll['municipality_id_fact'])->first();
        $customer = new User($customerAll->toArray());
        
        // Customer company
        $customer->company = new Company($customerAll->toArray());

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
        $invoice = $this->createXML(compact('user', 'company', 'customer', 'taxTotals', 'withHoldingTaxTotal', 'resolution', 'paymentForm', 'typeDocument', 'invoiceLines', 'allowanceCharges', 'legalMonetaryTotals', 'date', 'time', 'notes', 'typeoperation', 'orderreference', 'prepaidpayment', 'delivery', 'deliveryparty', 'request', 'idcurrency', 'calculationrate', 'calculationratedate', 'healthfields'));
        //echo htmlentities($invoice->saveXML());
        //json_encode($invoice);


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
            $signInvoice->GuardarEn = $request->GuardarEn."\\FE-{$resolution->next_consecutive}.xml";
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
            $signInvoice->GuardarEn = storage_path("app/public/{$company->identification_number}/FE-{$resolution->next_consecutive}.xml");  //direccion local para guardar el archivo xml signInvoice->GuardarEn = app/public/1094955142/FE-SETUP994411000.xml
        }

        // enviar documento
        $sendTestSetAsync = new SendTestSetAsync($company->certificate->path, $company->certificate->password);
        $sendTestSetAsync->To = $company->software->url;
        $sendTestSetAsync->fileName = "{$resolution->next_consecutive}.xml";

        if ($request->GuardarEn){                                                   // firma del envio
          $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signInvoice->sign($invoice), $request->GuardarEn."\\FES-{$resolution->next_consecutive}");
        }else{                                                              // se enia documento firmado y el camino en donde se va aguardar el documento firmado
          $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signInvoice->sign($invoice), storage_path("app/public/{$company->identification_number}/FES-{$resolution->next_consecutive}"));
        }
        $sendTestSetAsync->testSetId = $testSetId;

        $QRStr = $this->createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signInvoice->ConsultarCUFE(), "INVOICE", $withHoldingTaxTotal, $notes, $healthfields);
            
        return [
                'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
                'ResponseDian' => $sendTestSetAsync->signToSend(storage_path("app/public/{$company->identification_number}/ReqFE-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaFE-{$resolution->next_consecutive}.xml")), //enviar documento firmado y obtener su respuesta.
                //'invoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/FES-{$resolution->next_consecutive}.xml"))), //obtener en memoria el documento firmado digitalmente .xml
                //'zipinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/FES-{$resolution->next_consecutive}.zip"))), //obtener en memoria el ZIP del documento firmado digitalmente
                //'unsignedinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/FE-{$resolution->next_consecutive}.xml"))), //obtener en memoria el documento sin firmar .xml
                //'reqfe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqFE-{$resolution->next_consecutive}.xml"))),
                //'rptafe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaFE-{$resolution->next_consecutive}.xml"))),  //respuesta .xml del numero del zip
                'urlinvoicexml'=>"FES-{$resolution->next_consecutive}.xml",
                'urlinvoicepdf'=>"FES-{$resolution->next_consecutive}.pdf",
                'urlinvoiceattached'=>"Attachment-{$resolution->next_consecutive}.xml",
                'cufe' => $signInvoice->ConsultarCUFE(),
                'QRStr' => $QRStr,
                'certificate_days_left' => $certificate_days_left,
                'resolution_days_left' => $this->days_between_dates(Carbon::now()->format('Y-m-d'), $resolution->date_to),
            ];
        
    } //fin testSetStore invoice


}
