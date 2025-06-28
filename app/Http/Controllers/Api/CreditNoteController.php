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
use ubl21dian\Templates\SOAP\SendBillSync;
use ubl21dian\Templates\SOAP\SendTestSetAsync;
use ubl21dian\XAdES\SignAttachedDocument;
use ubl21dian\XAdES\SignCreditNote;

class CreditNoteController extends Controller
{
    use DocumentTrait;
    
    public function store(CreditNoteRequest $request, $testSetId)
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
            $signCreditNote->GuardarEn = $request->GuardarEn."\\{$pf}-{$resolution->next_consecutive}.xml"; //obtiene el valor del campo virtual next_consecutive por medio de getNextConsecutiveAttribute.
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
            $signCreditNote->GuardarEn = storage_path("app/public/{$company->identification_number}/{$pf}-{$resolution->next_consecutive}.xml");  //direccion local para guardar el archivo xml signInvoice->GuardarEn = app/public/1094955142/FE-SETUP994411000.xml
        }

        $sendBillSync = new SendBillSync($company->certificate->path, $company->certificate->password);
        if($is_eqdoc) //si es documento equivalente
            $sendBillSync->To = $company->software->url_eqdocs;
        else
            $sendBillSync->To = $company->software->url;

        $sendBillSync->fileName = "{$resolution->next_consecutive}.xml";
        
        if ($request->GuardarEn){
            $sendBillSync->contentFile = $this->zipBase64($company, $resolution, $signCreditNote->sign($crediNote), $request->GuardarEn."\\{$pfs}-{$resolution->next_consecutive}");
        }else{
            $sendBillSync->contentFile = $this->zipBase64($company, $resolution, $signCreditNote->sign($crediNote), storage_path("app/public/{$company->identification_number}/{$pfs}-{$resolution->next_consecutive}"));
        }

        $QRStr = $this->createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signCreditNote->ConsultarCUDE(), "NC", $withHoldingTaxTotal, $notes, $healthfields);

        $filename = '';
        $respuestadian = '';
        $typeDocument = TypeDocument::findOrFail(7); //tipo de documento es AttachedDocument code = 89, prefix = at

        $ar = new \DOMDocument;

        try{
            $respuestadian = $sendBillSync->signToSend(storage_path("app/public/{$company->identification_number}/Req{$pf}-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/Rpta{$pf}-{$resolution->next_consecutive}.xml"));
                if(isset($respuestadian->html))
                    return ['success' => false, 'message' => "El servicio DIAN no esta disponible en el momento, intente mas tarde."];
            
            if($respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == 'true'){
                //nombre del attacheddocument colocando 'ad'
                $filename = str_replace('nc', 'ad', str_replace('ads', 'ad', str_replace('dse', 'ad', str_replace('ni', 'ad', str_replace('nd', 'ad', str_replace('ncq', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlFileName)))))));
                if($request->atacheddocument_name_prefix)
                    $filename = $request->atacheddocument_name_prefix.$filename;
                $cufecude = $respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlDocumentKey;
                //$invoice_doc->state_document_id = 1;
                //$invoice_doc->cufe = $cufecude;
                //$invoice_doc->save();

                //Obtener XML firmado, partiendo del nombre de la respuesta de la Dian
                $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlFileName.".xml"));
                if(strpos($signedxml, "</Invoice>") > 0)
                    $td = '/Invoice';
                else
                    if(strpos($signedxml, "</CreditNote>") > 0)
                        $td = '/CreditNote';
                    else
                        $td = '/DebitNote';
                //Obtener XML en decodificado, partiendo de la respuesta de la Dian en base 64
                $appresponsexml = base64_decode($respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlBase64Bytes);
                $ar->loadXML($appresponsexml);
                $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");

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
                'send_email_success' => (null !== $invoice && $request->sendmail == true) ?? $invoice[0]->send_email_success == 1,
                'send_email_date_time' => (null !== $invoice && $request->sendmail == true) ?? Carbon::now()->format('Y-m-d H:i'),
                'ResponseDian' => $respuestadian,
                'invoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/{$pfs}-{$resolution->next_consecutive}.xml"))),
                'zipinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/{$pfs}-{$resolution->next_consecutive}.zip"))),
                'unsignedinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/{$pf}-{$resolution->next_consecutive}.xml"))),
                'reqfe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/Req{$pf}-{$resolution->next_consecutive}.xml"))),
                'rptafe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/Rpta{$pf}-{$resolution->next_consecutive}.xml"))),
                'attacheddocument'=>base64_encode($at),
                'urlinvoicexml'=>"{$pfs}-{$resolution->next_consecutive}.xml",
                'urlinvoicepdf'=>"{$pfs}-{$resolution->next_consecutive}.pdf",
                'urlinvoiceattached'=>"{$filename}.xml",
                'cude' => $signCreditNote->ConsultarCUDE(),
                'QRStr' => $QRStr,
                'certificate_days_left' => $certificate_days_left,
            ];

    }


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
            $signCreditNote->GuardarEn = $request->GuardarEn."\\{$pf}-{$resolution->next_consecutive}.xml"; //obtiene el valor del campo virtual next_consecutive por medio de getNextConsecutiveAttribute.
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
            $signCreditNote->GuardarEn = storage_path("app/public/{$company->identification_number}/{$pf}-{$resolution->next_consecutive}.xml");  //direccion local para guardar el archivo xml signInvoice->GuardarEn = app/public/1094955142/FE-SETUP994411000.xml
        }

        
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
        

        $QRStr = $this->createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signCreditNote->ConsultarCUDE(), "NC", $withHoldingTaxTotal, $notes, $healthfields);


        return [
            'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
            'ResponseDian' => $sendTestSetAsync->signToSend(storage_path("app/public/{$company->identification_number}/Req{$pf}-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/Rpta{$pf}-{$resolution->next_consecutive}.xml")),
            'invoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/{$pfs}-{$resolution->next_consecutive}.xml"))),
            'zipinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/{$pfs}-{$resolution->next_consecutive}.zip"))),
            'unsignedinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/{$pf}-{$resolution->next_consecutive}.xml"))),
            'reqfe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/Req{$pf}-{$resolution->next_consecutive}.xml"))),
            'rptafe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/Rpta{$pf}-{$resolution->next_consecutive}.xml"))),
            'urlinvoicexml'=>"{$pfs}-{$resolution->next_consecutive}.xml",
            'urlinvoicepdf'=>"{$pfs}-{$resolution->next_consecutive}.pdf",
            'urlinvoiceattached'=>"Attachment-{$resolution->next_consecutive}.xml",
            'cude' => $signCreditNote->ConsultarCUDE(),
            'QRStr' => $QRStr,
            'certificate_days_left' => $certificate_days_left,
        ];
    }
}
