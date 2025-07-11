<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Api\StatusRequest;
use Illuminate\Support\Facades\Config;
use App\Mail\InvoiceMail;
use App\Mail\PayrollMail;
use App\Models\Company;
use App\Models\TypeDocument;
use App\Models\User;
use App\Models\Resolution;
use App\Models\TypeDocumentIdentification;
use App\Models\TypeLiability;
use App\Traits\DocumentTrait;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Employee;
use App\Models\DocumentPayroll;
use ubl21dian\Templates\SOAP\GetStatusZip;
use ubl21dian\XAdES\SignAttachedDocument;
use Illuminate\Support\Facades\Mail;


class StateController extends Controller
{
    use DocumentTrait;
    
    public function zip(StatusRequest $request, $trackId, $GuardarEn = false)
    {
       // User
        $user = auth()->user();
        $smtp_parameters = collect($request->smtp_parameters);
        if(isset($request->smtp_parameters)){
            Config::set('mail.host', $smtp_parameters->toArray()['host']);
            Config::set('mail.port', $smtp_parameters->toArray()['port']);
            Config::set('mail.username', $smtp_parameters->toArray()['username']);
            Config::set('mail.password', $smtp_parameters->toArray()['password']);
            Config::set('mail.encryption', $smtp_parameters->toArray()['encryption']);
        }
        else
            if($user->validate_mail_server()){
                Config::set('mail.host', $user->mail_host);
                Config::set('mail.port', $user->mail_port);
                Config::set('mail.username', $user->mail_username);
                Config::set('mail.password', $user->mail_password);
                Config::set('mail.encryption', $user->mail_encryption);
            }

            // User company
        $company = $user->company;

        // Verify Certificate
        $certificate_days_left = 0;
        $c = $this->verify_certificate();
        if(!$c['success'])
            return $c;
        else
            $certificate_days_left = $c['certificate_days_left'];

        if($request->is_payroll && $request->is_eqdoc)
            return [
                'success' => false,
                'message' => "Debe especificar un solo documento a validar NOMINA o DOCUMENTO EQUIVALENTE, si no especifica ninguno, por defecto se validara FACTURA ELECTRONICA."
            ];

        if($request->is_payroll)
            $getStatusZip = new GetStatusZip($user->company->certificate->path, $user->company->certificate->password, $user->company->software->url_payroll);
        else
            if($request->is_eqdoc)
                $getStatusZip = new GetStatusZip($user->company->certificate->path, $user->company->certificate->password, $user->company->software->url_eqdocs);
            else
                $getStatusZip = new GetStatusZip($user->company->certificate->path, $user->company->certificate->password, $user->company->software->url);

        $getStatusZip->trackId = $trackId;
        $GuardarEn = str_replace("_", "\\", $GuardarEn);

        if ($GuardarEn){
            if (!is_dir($GuardarEn)) {
                mkdir($GuardarEn);
            }
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
        }

        $respuestadian = '';
        $typeDocument = TypeDocument::findOrFail(7);
        $resolution = NULL;
        $customer = NULL;
//        $xml = new \DOMDocument;
        $ar = new \DOMDocument;

        if ($GuardarEn){
            try{
                $respuestadian = $getStatusZip->signToSend($GuardarEn."\\ReqZIP-".$trackId.".xml")->getResponseToObject($GuardarEn."\\RptaZIP-".$trackId.".xml");
                if(isset($respuestadian->html))
                    return [
                        'success' => false,
                        'message' => "El servicio DIAN no se encuentra disponible en el momento, reintente mas tarde..."
                    ];

                if($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == 'true'){
                    if(isset($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName->_attributes))
                    {
                        $invoicenumber = $this->InvoiceByZipKey($company->identification_number, $trackId);
                        $signedxml = file_get_contents(storage_path("app/public/{$company->identification_number}/FES-".$invoicenumber));
                    }
                    else
                        $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName.".xml"));

                    if(strpos($signedxml, "</Invoice>") > 0)
                        $td = '/Invoice';
                    else
                        if(strpos($signedxml, "</CreditNote>") > 0)
                            $td = '/CreditNote';
                        else
                            if(strpos($signedxml, "</DebitNote>") > 0)
                                $td = '/DebitNote';
                            else
                                if(strpos($signedxml, "</NominaIndividual>") > 0)
                                    $td = '/NominaIndividual';
                                else
                                    if(strpos($signedxml, "</NominaIndividualDeAjuste>") > 0)
                                        $td = '/NominaIndividualDeAjuste';

//                    $xml->loadXML($signedxml);

                    $filename = str_replace('pos', 'ad', str_replace('ads', 'ad', str_replace('dse', 'ad', str_replace('ni', 'ad', str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName)))))));
                    if($request->atacheddocument_name_prefix)
                        $filename = $request->atacheddocument_name_prefix.$filename;

                    $cufecude = $respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlDocumentKey;

//                    if ($td == '/NominaIndividual' || $td == 'NominaIndividualDeAjuste')
//                        $cufecude = $this->getTag($signedxml, 'InformacionGeneral', 0, 'CUNE');
//                    else
//                        $cufecude = $this->ValueXML($signedxml, $td."/cbc:UUID/");
                    $appresponsexml = base64_decode($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlBase64Bytes);
                    $ar->loadXML($appresponsexml);
                    $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                    $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                    if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                        $document_number = $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Numero');
                    else
                        $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");

                    if($td == '/Invoice')
                        if(isset($this->getTag($signedxml, 'Prefix', 0)->nodeValue))
                            $resolution = Resolution::where('prefix', $this->getTag($signedxml, 'Prefix', 0)->nodeValue)->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
                        else
                            $resolution = Resolution::where('prefix', NULL)->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
//                        $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
                    else
                        if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                            $resolution = Resolution::where('prefix', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))->firstOrFail();
                        else
                            $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->firstOrFail();

                    $resolution->document_number = $document_number;
                    // Create XML AttachedDocument
                    $at = '';
                    if($td != '/NominaIndividual' && $td != '/NominaIndividualDeAjuste'){
                        if($this->valueXML($signedxml, $td."/cbc:ProfileID/") != 'DIAN 2.1: documento soporte en adquisiciones efectuadas a no obligados a facturar.' && $this->valueXML($signedxml, $td."/cbc:ProfileID/") != 'DIAN 2.1: Nota de ajuste al documento soporte en adquisiciones efectuadas a sujetos no obligados a expedir factura o documento equivalente')
                            $u = ['name' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name/"),
                                  'email' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                                  'identification_number' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"),
                                  'phone' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                                  'address' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line/"),
                                  'type_liability_id' => TypeLiability::where('code', $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode/"))->firstOrFail()->id,
                                  'type_regime_id' => "2",
                                  'type_document_identification_id' => TypeDocumentIdentification::where('code', 'Like', '%'.$this->getTag($signedxml, "CompanyID", 2, "schemeName").'%')->firstOrFail()->id,
                                  'dv' => $this->getTag($signedxml, "CompanyID", 2, "schemeID"),
                                 ];
                        else
                            $u = ['name' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name/"),
                                  'email' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                                  'identification_number' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"),
                                  'phone' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                                  'address' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line/"),
                                  'type_liability_id' => TypeLiability::where('code', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode/"))->firstOrFail()->id,
                                  'type_regime_id' => "2",
                                  'type_document_identification_id' => TypeDocumentIdentification::where('code', 'Like', '%'.$this->getTag($signedxml, "CompanyID", 1, "schemeName").'%')->firstOrFail()->id,
                                  'dv' => $this->getTag($signedxml, "CompanyID", 1, "schemeID"),
                                 ];

                        $customer = new user($u);
                        $customer->company = new Company($u);
                        $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));

                        // Signature XML
                        $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                        $signAttachedDocument->GuardarEn = $GuardarEn."\\{$filename}.xml";

                        $at = $signAttachedDocument->sign($attacheddocument)->xml;
//                        $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
//                        $file = fopen($GuardarEn."\\Attachment-".$this->valueXML($signedxml, $td."/cbc:ID/").".xml", "w");
                        $file = fopen($GuardarEn."\\{$filename}".".xml", "w");
                        fwrite($file, $at);
                        fclose($file);
                        if($this->valueXML($signedxml, $td."/cbc:ProfileID/") != 'DIAN 2.1: documento soporte en adquisiciones efectuadas a no obligados a facturar.' && $this->valueXML($signedxml, $td."/cbc:ProfileID/") != 'DIAN 2.1: Nota de ajuste al documento soporte en adquisiciones efectuadas a sujetos no obligados a expedir factura o documento equivalente')
                            $customer = Customer::findOrFail($this->valueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"));
                        else
                            $customer = Customer::findOrFail($this->valueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID/"));
                        $invoice = Document::where('identification_number', '=', $company->identification_number)
                                           ->where('customer', '=', $customer->identification_number)
                                           ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                           ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                           ->where('state_document_id', '=', 0)->get();
                        if(count($invoice) > 0){
                            $invoice[0]->state_document_id = 1;
                            $invoice[0]->cufe = $cufecude;
                            $invoice[0]->save();
                        }
                        if(isset($request))
                            if($request->sendmail){
                                $invoice = Document::where('identification_number', '=', $company->identification_number)
                                                   ->where('customer', '=', $customer->identification_number)
                                                   ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                                   ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                                   ->where('state_document_id', '=', 1)->get();
                                if(count($invoice) > 0 && $customer->identification_number != '222222222222'){
                                    try{
                                        Mail::to($customer->email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename));
                                        if($request->senmailtome)
                                            Mail::to($user->email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename));
                                        if($request->email_cc_list){
                                            foreach($request->email_cc_list as $email)
                                                Mail::to($email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename));
                                        }
                                        $invoice[0]->send_email_success = 1;
                                        $invoice[0]->save();
                                    } catch (\Exception $m) {
                                        \Log::debug($m->getMessage());
                                    }
                                }
                            }
                    }
                    else{
                        $worker = Employee::where('identification_number',  '=', $this->getTag($signedxml, 'Trabajador', 0, 'NumeroDocumento'))->firstOrFail();
                        $payroll = DocumentPayroll::where('identification_number', '=', $company->identification_number)
                                                  ->where('employee_id', '=', $worker->identification_number)
                                                  ->where('prefix', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))
                                                  ->where('consecutive', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Consecutivo'))
                                                  ->where('state_document_id', '=', 0)->get();
                        if(count($payroll) > 0){
                            $payroll[0]->state_document_id = 1;
                            $payroll[0]->cune = $cufecude;
                            $payroll[0]->save();
                        }
                        if(isset($request->sendmail) && (!$this->getTag($signedxml, 'EliminandoPredecesor', 0, 'CUNEPred'))){
                            if($request->sendmail){
                                $payroll = DocumentPayroll::where('identification_number', '=', $company->identification_number)
                                                            ->where('employee_id', '=', $worker->identification_number)
                                                            ->where('prefix', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))
                                                            ->where('consecutive', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Consecutivo'))
                                                            ->where('state_document_id', '=', 1)->get();
                                if(count($payroll) > 0){
                                    try{
                                        Mail::to($worker->email)->send(new PayrollMail($payroll, $worker, $company, FALSE, $filename, $request));
                                        if($request->sendmailtome)
                                            Mail::to($user->email)->send(new PayrollMail($payroll, $worker, $company, FALSE, $filename, $request));
                                    } catch (\Exception $m) {
                                        \Log::debug($m->getMessage());
                                    }
                                }
                            }
                        }
                    }
                }
                else
                  $at = '';
            } catch (\Exception $e) {
                return $e->getMessage().' '.preg_replace("/[\r\n|\n|\r]+/", "", json_encode($respuestadian));
            }
            return [
                'message' => 'Consulta generada con éxito',
                'ResponseDian' => $respuestadian,
                'reqzip'=>base64_encode(file_get_contents($GuardarEn."\\ReqZIP-{$trackId}.xml")),
                'rptazip'=>base64_encode(file_get_contents($GuardarEn."\\RptaZIP-{$trackId}.xml")),
                'attacheddocument'=>base64_encode($at),
                'cufecude'=>$cufecude,
                'certificate_days_left' => $certificate_days_left,
            ];
        }else{
            try{
                $respuestadian = $getStatusZip->signToSend(storage_path("app/public/{$company->identification_number}/ReqZIP-".$trackId.".xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaZIP-".$trackId.".xml"));
                if(isset($respuestadian->html))
                    return [
                        'success' => false,
                        'message' => "El servicio DIAN no se encuentra disponible en el momento, reintente mas tarde..."
                    ];

                if($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == 'true'){
                    if(isset($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName->_attributes))
                    {
                        $invoicenumber = $this->InvoiceByZipKey($company->identification_number, $trackId);
                        $signedxml = file_get_contents(storage_path("app/public/{$company->identification_number}/FES-".$invoicenumber));
                    }
                    else
                        $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName.".xml"));
//                    $xml->loadXML($signedxml);

                    if(strpos($signedxml, "</Invoice>") > 0)
                        $td = '/Invoice';
                    else
                        if(strpos($signedxml, "</CreditNote>") > 0)
                            $td = '/CreditNote';
                        else
                            if(strpos($signedxml, "</DebitNote>") > 0)
                                $td = '/DebitNote';
                            else
                                if(strpos($signedxml, "</NominaIndividual>") > 0)
                                    $td = '/NominaIndividual';
                                else
                                    if(strpos($signedxml, "</NominaIndividualDeAjuste>") > 0)
                                        $td = '/NominaIndividualDeAjuste';

//                    if(isset($respuestadian->Envelope->Body->GetStatusZip;Response->GetStatusZipResult->DianResponse->XmlFileName->_attributes))
//                        $xml = $this->readXML(storage_path("app/public/{$company->identification_number}/FES-".$invoicenumber));
//                    else
//                        $xml = $this->readXML(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName.".xml"));

//                    return $this->ValueXML($signedxml, '/CreditNote/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:Name/');

                    $filename = str_replace('pos', 'ad', str_replace('ads', 'ad', str_replace('dse', 'ad', str_replace('ni', 'ad', str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName)))))));
                    if($request->atacheddocument_name_prefix)
                        $filename = $request->atacheddocument_name_prefix.$filename;

                    $cufecude = $respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlDocumentKey;

//                    if($td == '/NominaIndividual' || $td == 'NominaIndividualDeAjuste')
//                        $cufecude = $this->getTag($signedxml, 'InformacionGeneral', 0, 'CUNE');
//                    else
//                        $cufecude = $this->ValueXML($signedxml, $td."/cbc:UUID/");

                    $appresponsexml = base64_decode($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlBase64Bytes);
                    $ar->loadXML($appresponsexml);
                    $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                    $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                    if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                        $document_number = $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Numero');
                    else
                        $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");

                    if($td == '/Invoice')
                        if(isset($this->getTag($signedxml, 'Prefix', 0)->nodeValue))
                            $resolution = Resolution::where('prefix', $this->getTag($signedxml, 'Prefix', 0)->nodeValue)->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
                        else
                            $resolution = Resolution::where('prefix', NULL)->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
//                        $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
                    else
                        if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                            $resolution = Resolution::where('prefix', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))->firstOrFail();
                        else
                            $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->firstOrFail();

                    $resolution->document_number = $document_number;
                      // Create XML AttachedDocument
                    $at = '';
                    if($td != '/NominaIndividual' && $td != '/NominaIndividualDeAjuste'){
                        if($this->valueXML($signedxml, $td."/cbc:ProfileID/") != 'DIAN 2.1: documento soporte en adquisiciones efectuadas a no obligados a facturar.' && $this->valueXML($signedxml, $td."/cbc:ProfileID/") != 'DIAN 2.1: Nota de ajuste al documento soporte en adquisiciones efectuadas a sujetos no obligados a expedir factura o documento equivalente')
                            $u = ['name' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name/"),
                                  'email' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                                  'identification_number' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"),
                                  'phone' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                                  'address' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line/"),
                                  'type_liability_id' => TypeLiability::where('code', $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode/"))->firstOrFail()->id,
                                  'type_regime_id' => "2",
                                  'type_document_identification_id' => TypeDocumentIdentification::where('code', 'Like', '%'.$this->getTag($signedxml, "CompanyID", 2, "schemeName").'%')->firstOrFail()->id,
                                  'dv' => $this->getTag($signedxml, "CompanyID", 2, "schemeID"),
                                 ];
                        else
                            $u = ['name' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name/"),
                                  'email' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                                  'identification_number' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"),
                                  'phone' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                                  'address' => $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line/"),
                                  'type_liability_id' => TypeLiability::where('code', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode/"))->firstOrFail()->id,
                                  'type_regime_id' => "2",
                                  'type_document_identification_id' => TypeDocumentIdentification::where('code', 'Like', '%'.$this->getTag($signedxml, "CompanyID", 1, "schemeName").'%')->firstOrFail()->id,
                                  'dv' => $this->getTag($signedxml, "CompanyID", 1, "schemeID"),
                                 ];

                        $customer = new user($u);
                        $customer->company = new Company($u);
                        $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));
                        // Signature XML
                        $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                        $signAttachedDocument->GuardarEn = storage_path("app/public/{$company->identification_number}/{$filename}.xml");

                        $at = $signAttachedDocument->sign($attacheddocument)->xml;
//                        $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
                        $file = fopen(storage_path("app/public/{$company->identification_number}/{$filename}".".xml"), "w");
                        fwrite($file, $at);
                        fclose($file);
                        if($this->valueXML($signedxml, $td."/cbc:ProfileID/") != 'DIAN 2.1: documento soporte en adquisiciones efectuadas a no obligados a facturar.' && $this->valueXML($signedxml, $td."/cbc:ProfileID/") != 'DIAN 2.1: Nota de ajuste al documento soporte en adquisiciones efectuadas a sujetos no obligados a expedir factura o documento equivalente')
                            $customer = Customer::findOrFail($this->valueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"));
                        else
                            $customer = Customer::findOrFail($this->valueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID/"));
                        $invoice = Document::where('identification_number', '=', $company->identification_number)
                                           ->where('customer', '=', $customer->identification_number)
                                           ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                           ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                           ->where('state_document_id', '=', 0)->get();
                        if(count($invoice) > 0){
                            $invoice[0]->state_document_id = 1;
                            $invoice[0]->cufe = $cufecude;
                            $invoice[0]->save();
                        }
                        if(isset($request))
                            if($request->sendmail){
                                $invoice = Document::where('identification_number', '=', $company->identification_number)
                                                   ->where('customer', '=', $customer->identification_number)
                                                   ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                                   ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                                   ->where('state_document_id', '=', 1)->get();
                                if(count($invoice) > 0 && $customer->identification_number != '222222222222'){
                                    try{
                                        Mail::to($customer->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, TRUE));
                                        if($request->sendmailtome)
                                            Mail::to($user->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE));
                                        if($request->email_cc_list){
                                            foreach($request->email_cc_list as $email)
                                                Mail::to($email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE));
                                        }
                                        $invoice[0]->send_email_success = 1;
                                        $invoice[0]->save();
                                    } catch (\Exception $m) {
                                        \Log::debug($m->getMessage());
                                    }
                                }
                            }
                    }
                    else{
                        $worker = Employee::where('identification_number',  '=', $this->getTag($signedxml, 'Trabajador', 0, 'NumeroDocumento'))->firstOrFail();
                        $payroll = DocumentPayroll::where('identification_number', '=', $company->identification_number)
                                                  ->where('employee_id', '=', $worker->identification_number)
                                                  ->where('prefix', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))
                                                  ->where('consecutive', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Consecutivo'))
                                                  ->where('state_document_id', '=', 0)->get();
                        if(count($payroll) > 0){
                            $payroll[0]->state_document_id = 1;
                            $payroll[0]->cune = $cufecude;
                            $payroll[0]->save();
                        }
                        if(isset($request->sendmail) && (!$this->getTag($signedxml, 'EliminandoPredecesor', 0, 'CUNEPred'))){
                            if($request->sendmail){
                                $payroll = DocumentPayroll::where('identification_number', '=', $company->identification_number)
                                                            ->where('employee_id', '=', $worker->identification_number)
                                                            ->where('prefix', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))
                                                            ->where('consecutive', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Consecutivo'))
                                                            ->where('state_document_id', '=', 1)->get();
                                if(count($payroll) > 0){
                                    try{
                                        Mail::to($worker->email)->send(new PayrollMail($payroll, $worker, $company, FALSE, $filename, $request));
                                        if($request->sendmailtome)
                                            Mail::to($user->email)->send(new PayrollMail($payroll, $worker, $company, FALSE, $filename, $request));
                                    } catch (\Exception $m) {
                                        \Log::debug($m->getMessage());
                                    }
                                }
                            }
                        }
                    }
                }
                else
                    $at = '';
            } catch (\Exception $e) {
                return $e->getMessage().' '.preg_replace("/[\r\n|\n|\r]+/", "", json_encode($respuestadian));
            }
            return [
                'message' => 'Consulta generada con éxito',
                'ResponseDian' => $respuestadian,
                'reqzip'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqZIP-{$trackId}.xml"))),
                'rptazip'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaZIP-{$trackId}.xml"))),
                'attacheddocument'=>base64_encode($at),
                'certificate_days_left' => $certificate_days_left,
            ];
        }

    }
}
