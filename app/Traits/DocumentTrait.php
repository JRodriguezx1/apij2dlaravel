<?php

namespace App\Traits;

use App\Http\Controllers\Api\ConfigurationController;
use App\Models\Company;
use App\Models\Resolution;
use Exception;
use DOMDocument;
use InvalidArgumentException;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Storage;
use ubl21dian\Sign;
use ZipArchive;
use App\Custom\zipfileDIAN;
use App\Models\TypeDocument;
use Illuminate\Support\Facades\View;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


trait DocumentTrait
{
    public $ppp = '000';

    private $paymentFormDefault = [
        'payment_form_id' => 1,
        'payment_method_id' => 10,
    ];

    protected function getTag($document, $tagName, $item = 0, $attribute = NULL, $attribute_value = NULL) //utilizado en StateController
    {
        if (is_string($document)){
            $xml = $document;
            $document = new \DOMDocument;
            $document->loadXML($xml);
        }

        $tag = $document->documentElement->getElementsByTagName($tagName);

        if(is_null($tag->item(0)))return;

        if($attribute){
            if($attribute_value){
                $tag->item($item)->setAttribute($attribute, $attribute_value);
                return;
            }
            else {return $tag->item($item)->getAttribute($attribute); }
        }else{ return $tag->item($item); }
    }

    protected function validarDigVerifDIAN($nit)
    {
        if(is_numeric(trim($nit))){
            $secuencia = array(3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71);
            $d = str_split(trim($nit));
            krsort($d);
            $cont = 0;
            unset($val);
            foreach ($d as $key => $value) {
                $val[$cont] = $value * $secuencia[$cont];
                $cont++;
            }
            $suma = array_sum($val);
            $div = intval($suma / 11);
            $num = $div * 11;
            $resta = $suma - $num;
            if ($resta == 1)
                return $resta;
            else
                if($resta != 0){
                    return 11 - $resta;
                }else{ return $resta; }
        }else{
            return FALSE;
        }
    }


    //VERIFICAR CERTIFICADO, LLANANDO AL CONTROLADOR CONFIGURATIONCONTROLLER
    function verify_certificate($user = FALSE){
        $c = new ConfigurationController();
        $certificate_end_date = new DateTime(Carbon::parse(str_replace("/", "-", $c->CertificateEndDate($user)))->format('Y-m-d'));
        $actual_date = new DateTime(Carbon::now()->format('Y-m-d'));
        $interval = $actual_date->diff($certificate_end_date);
        $certificate_days_left = 0;
        if($interval->days == 0 || $interval->invert == 1)
            return [
                'success' => false,
                'message' => 'El certificado digital ya se encuentra vencido...',
                'expiration_date' => $certificate_end_date,
                'certificate_days_left' => 0,
            ];
        else
            return [
                'success' => true,
                'message' => 'El certificado digital es valido...',
                'expiration_date' => $certificate_end_date,
                'certificate_days_left' => $interval->days,
            ];
    }
    
    
    //CREANDO EL XML
    protected function createXML(array $data)
    {
        if($data['typeDocument']['code'] === '01' or $data['typeDocument']['code'] === '02' or $data['typeDocument']['code'] === '03' or $data['typeDocument']['code'] === '05' or $data['typeDocument']['code'] === '95' or $data['typeDocument']['code'] === '91' or $data['typeDocument']['code'] === '92' or $data['typeDocument']['code'] === '20' or $data['typeDocument']['code'] === '93' or $data['typeDocument']['code'] === '94'){
            if($data['typeDocument']['code'] === '20' or $data['typeDocument']['code'] === '93' or $data['typeDocument']['code'] === '94'){ //si es documento equivalente electronico de etiquete POS o ND o NC al documneto equivalente
                if($data['company']['eqdocs_type_environment_id'] == 2)  //tipo de ambiente para el documento equivalente o ND o NC del documento equivalente
                    $urlquery = 'https://catalogo-vpfe-hab.dian.gov.co';
                else
                    $urlquery = 'https://catalogo-vpfe.dian.gov.co';
            }
            else{
                if($data['company']['type_environment_id'] == 2)
                    $urlquery = 'https://catalogo-vpfe-hab.dian.gov.co';
                else
                    $urlquery = 'https://catalogo-vpfe.dian.gov.co';
            }
            if($data['typeDocument']['code'] === '01' or $data['typeDocument']['code'] === '02' or $data['typeDocument']['code'] === '03' or $data['typeDocument']['code'] === '20')
                if(isset($data['request']['tax_totals'][0]['tax_amount']))
                    $QRCode = 'NumFac: '.$data['resolution']['next_consecutive'].PHP_EOL.'FecFac: '.$data['date'].PHP_EOL.'NitFac: '.$data['user']['company']['identification_number'].PHP_EOL.'DocAdq: '.$data['customer']['company']['identification_number'].PHP_EOL.'ValFac: '.$data['legalMonetaryTotals']['tax_exclusive_amount'].PHP_EOL.'ValIva: '.$data['request']['tax_totals'][0]['tax_amount'].PHP_EOL.'ValOtroIm: '.$data['legalMonetaryTotals']['allowance_total_amount'].PHP_EOL.'ValTotal: '.$data['legalMonetaryTotals']['payable_amount'].PHP_EOL.'CUFE: -----CUFECUDE-----'.PHP_EOL.$urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
                else
                    $QRCode = 'NumFac: '.$data['resolution']['next_consecutive'].PHP_EOL.'FecFac: '.$data['date'].PHP_EOL.'NitFac: '.$data['user']['company']['identification_number'].PHP_EOL.'DocAdq: '.$data['customer']['company']['identification_number'].PHP_EOL.'ValFac: '.$data['legalMonetaryTotals']['tax_exclusive_amount'].PHP_EOL.'ValIva: '.'0.00'.PHP_EOL.'ValOtroIm: '.$data['legalMonetaryTotals']['allowance_total_amount'].PHP_EOL.'ValTotal: '.$data['legalMonetaryTotals']['payable_amount'].PHP_EOL.'CUFE: -----CUFECUDE-----'.PHP_EOL.$urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
            else
                if($data['typeDocument']['code'] === '91' or $data['typeDocument']['code'] === '92' or $data['typeDocument']['code'] === '93' or $data['typeDocument']['code'] === '94')
                    if(isset($request->tax_totals[0]['tax_amount']))
                        if($data['typeDocument']['code'] === '93')
                            $QRCode = 'NumFac: '.$data['resolution']['next_consecutive'].PHP_EOL.'FecFac: '.$data['date'].PHP_EOL.'NitFac: '.$data['user']['company']['identification_number'].PHP_EOL.'DocAdq: '.$data['customer']['company']['identification_number'].PHP_EOL.'ValFac: '.$data['requestedMonetaryTotals']['tax_exclusive_amount'].PHP_EOL.'ValIva: '.$data['request']['tax_totals'][0]['tax_amount'].PHP_EOL.'ValOtroIm: '.$data['requestedMonetaryTotals']['allowance_total_amount'].PHP_EOL.'ValTotal: '.$data['requestedMonetaryTotals']['payable_amount'].PHP_EOL.'CUFE: -----CUFECUDE-----'.PHP_EOL.$urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
                        else
                            $QRCode = 'NumFac: '.$data['resolution']['next_consecutive'].PHP_EOL.'FecFac: '.$data['date'].PHP_EOL.'NitFac: '.$data['user']['company']['identification_number'].PHP_EOL.'DocAdq: '.$data['customer']['company']['identification_number'].PHP_EOL.'ValFac: '.$data['legalMonetaryTotals']['tax_exclusive_amount'].PHP_EOL.'ValIva: '.$data['request']['tax_totals'][0]['tax_amount'].PHP_EOL.'ValOtroIm: '.$data['legalMonetaryTotals']['allowance_total_amount'].PHP_EOL.'ValTotal: '.$data['legalMonetaryTotals']['payable_amount'].PHP_EOL.'CUFE: -----CUFECUDE-----'.PHP_EOL.$urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
                    else
                        if($data['typeDocument']['code'] === '93')
                            $QRCode = 'NumFac: '.$data['resolution']['next_consecutive'].PHP_EOL.'FecFac: '.$data['date'].PHP_EOL.'NitFac: '.$data['user']['company']['identification_number'].PHP_EOL.'DocAdq: '.$data['customer']['company']['identification_number'].PHP_EOL.'ValFac: '.$data['requestedMonetaryTotals']['tax_exclusive_amount'].PHP_EOL.'ValIva: 0.00'.PHP_EOL.'ValOtroIm: '.$data['requestedMonetaryTotals']['allowance_total_amount'].PHP_EOL.'ValTotal: '.$data['requestedMonetaryTotals']['payable_amount'].PHP_EOL.'CUFE: -----CUFECUDE-----'.PHP_EOL.$urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
                        else
                            $QRCode = 'NumFac: '.$data['resolution']['next_consecutive'].PHP_EOL.'FecFac: '.$data['date'].PHP_EOL.'NitFac: '.$data['user']['company']['identification_number'].PHP_EOL.'DocAdq: '.$data['customer']['company']['identification_number'].PHP_EOL.'ValFac: '.$data['legalMonetaryTotals']['tax_exclusive_amount'].PHP_EOL.'ValIva: 0.00'.PHP_EOL.'ValOtroIm: '.$data['legalMonetaryTotals']['allowance_total_amount'].PHP_EOL.'ValTotal: '.$data['legalMonetaryTotals']['payable_amount'].PHP_EOL.'CUFE: -----CUFECUDE-----'.PHP_EOL.$urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
                else
                    if($data['typeDocument']['code'] === '05' or $data['typeDocument']['code'] === '95')
                        $QRCode = $urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
                    else
                        $QRCode = 'NumFac: '.$data['resolution']['next_consecutive'].PHP_EOL.'FecFac: '.$data['date'].PHP_EOL.'NitFac: '.$data['user']['company']['identification_number'].PHP_EOL.'DocAdq: '.$data['customer']['company']['identification_number'].PHP_EOL.'ValFac: '.$data['requestedMonetaryTotals']['tax_exclusive_amount'].PHP_EOL.'ValIva: '.$data['request']['tax_totals'][0]['tax_amount'].PHP_EOL.'ValOtroIm: '.$data['requestedMonetaryTotals']['allowance_total_amount'].PHP_EOL.'ValTotal: '.$data['requestedMonetaryTotals']['payable_amount'].PHP_EOL.'CUFE: -----CUFECUDE-----'.PHP_EOL.$urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
            $data['QRCode'] = $QRCode;
        }
        else{
            if($data['typeDocument']['code'] === '88')
                $urlquery = 'https://catalogo-vpfe.dian.gov.co';
            else
                if($data['company']['payroll_type_environment_id'] == 2)
                    $urlquery = 'https://catalogo-vpfe-hab.dian.gov.co';
                else
                    $urlquery = 'https://catalogo-vpfe.dian.gov.co';
            $QRCode = $urlquery.'/document/searchqr?documentkey=-----CUFECUDE-----';
            $data['QRCode'] = $QRCode;
        }
        try {
            $DOMDocumentXML = new DOMDocument();
            $DOMDocumentXML->preserveWhiteSpace = false;
            $DOMDocumentXML->formatOutput = true;

            if(isset($data['request']['is_eqdoc']) && ($data['request']['is_eqdoc'] == true)){
                if($data['request']['is_eqdoc'] == true && $data['typeDocument']['code'] == 94)
                    $DOMDocumentXML->loadXML(view("xml.91", $data)->render());
                if($data['request']['is_eqdoc'] == true && $data['typeDocument']['code'] == 93)
                    $DOMDocumentXML->loadXML(view("xml.92", $data)->render());
            }
            else{
                $DOMDocumentXML->loadXML(view("xml.{$data['typeDocument']['code']}", $data)->render());  //aqui se busca la plantilla 01.blade para FE
            }
            if(isset($data['signedxml']) and ($data['typeDocument']['code'] === '89')){
                $rootNode = $DOMDocumentXML->documentElement;
                $nodeCDATAInvoice = $rootNode->getElementsByTagName("ExternalReference")->item(0);
                $elementCDATA = $DOMDocumentXML->createElement('cbc:Description');
                $CDATA = $DOMDocumentXML->createCDATASection($data['signedxml']);
                $elementCDATA->appendChild($CDATA);
                $nodeCDATAInvoice->appendChild($elementCDATA);
            }

            if(isset($data['appresponsexml']) and ($data['typeDocument']['code'] === '89')){
                $rootNode = $DOMDocumentXML->documentElement;
                $nodeCDATAAppResponse = $rootNode->getElementsByTagName("ExternalReference")->item(1);
                $elementCDATA = $DOMDocumentXML->createElement('cbc:Description');
                $CDATA = $DOMDocumentXML->createCDATASection($data['appresponsexml']);
                $elementCDATA->appendChild($CDATA);
                $nodeCDATAAppResponse->appendChild($elementCDATA);
            }

            return $DOMDocumentXML;
        } catch (InvalidArgumentException $e) {
            throw new Exception("The API does not support the type of document '{$data['typeDocument']['name']}' Error: {$e->getMessage()}");
        } catch (Exception $e) {
            throw new Exception("Error: {$e->getMessage()}");
        }
    }

    //CREANDO EL ZIP DEL XML
    protected function zipBase64(Company $company, Resolution $resolution, Sign $sign, $GuardarEn = false, $batch = false){ //GuardarEn = app/public/1094955142/FES-SETUP994411000
        
        $dir = preg_replace("/[\r\n|\n|\r]+/", "", "zip/{$resolution->company_id}");
        $nameXML = preg_replace("/[\r\n|\n|\r]+/", "", $this->getFileName($company, $resolution));
        if ($batch)
          $nameZip = $batch.".zip";
        else
          $nameZip = preg_replace("/[\r\n|\n|\r]+/", "", $this->getFileName($company, $resolution, 6, '.zip'));

        $this->pathZIP = preg_replace("/[\r\n|\n|\r]+/", "", "app/zip/{$resolution->company_id}/{$nameZip}");

        Storage::put(preg_replace("/[\r\n|\n|\r]+/", "", "xml/{$resolution->company_id}/{$nameXML}"), $sign->xml);  //guardamos el xml firmado en app/xml/17/nombre.xml

        if (!Storage::has($dir)) {
            Storage::makeDirectory($dir);
        }

        $zip = new ZipArchive();

        $result_code = $zip->open(storage_path($this->pathZIP), ZipArchive::CREATE);
        if($result_code !== true){
            $zip = new zipfileDIAN();
            $zip->add_file(implode("", file(preg_replace("/[\r\n|\n|\r]+/", "", storage_path("app/xml/{$resolution->company_id}/{$nameXML}")))), preg_replace("/[\r\n|\n|\r]+/", "", $nameXML));
			Storage::put(preg_replace("/[\r\n|\n|\r]+/", "", "zip/{$resolution->company_id}/{$nameZip}"), $zip->file());
        }
        else{
            $zip->addFile(preg_replace("/[\r\n|\n|\r]+/", "", storage_path("app/xml/{$resolution->company_id}/{$nameXML}")), preg_replace("/[\r\n|\n|\r]+/", "", $nameXML));
            $zip->close();
        }

        if ($GuardarEn){  //GuardarEn = app/public/1094955142/FEs-SETUP994411000
            copy(preg_replace("/[\r\n|\n|\r]+/", "", storage_path("app/xml/{$resolution->company_id}/{$nameXML}")), $GuardarEn.".xml");
            copy(preg_replace("/[\r\n|\n|\r]+/", "", storage_path($this->pathZIP)), $GuardarEn.".zip");
        }

        return $this->ZipBase64Bytes = base64_encode(file_get_contents(preg_replace("/[\r\n|\n|\r]+/", "", storage_path($this->pathZIP))));
    }


    //OBTENER NOMBRE PARA EL XML Y PARA EL ZIP
    protected function getFileName(Company $company, Resolution $resolution, $typeDocumentID = null, $extension = '.xml')
    {
        $date = now();
        $prefix = (is_null($typeDocumentID)) ? $resolution->type_document->prefix : TypeDocument::findOrFail($typeDocumentID)->prefix;

        $send = $company->send()->firstOrCreate([
            'year' => $date->format('Y'),
            'type_document_id' => $typeDocumentID ?? $resolution->type_document_id,
        ]);

        $name = "{$prefix}{$this->stuffedString($company->identification_number)}{$this->ppp}{$date->format('y')}{$this->stuffedString($send->next_consecutive ?? 1, 8)}{$extension}";

        $send->increment('next_consecutive');
        return $name;
    }

    
    protected function stuffedString($string, $length = 10, $padString = 0, $padType = STR_PAD_LEFT)
    {
        return str_pad($string, $length, $padString, $padType);
    }


    //COMPRIMIR EL XML Y PFD PARA ENVIAR POR EMAIL.
    protected function zipEmail($xml, $pdf)
    {
        $nameXML = preg_replace("/[\r\n|\n|\r]+/", "", $xml);
        $namePDF = preg_replace("/[\r\n|\n|\r]+/", "", $pdf);
        $nameZip = preg_replace("/[\r\n|\n|\r]+/", "", substr($xml, 0, strlen($xml) - 3)."zip");
        $nameAD = preg_replace("/[\r\n|\n|\r]+/", "", substr($xml, 0, strlen($xml) - 4));

        $zip = new ZipArchive();

        $result_code = $zip->open($nameZip, ZipArchive::CREATE);
        $zip->addFile($nameXML, basename($nameXML));
        $zip->addFile($namePDF, str_replace('xml', 'pdf', basename($nameXML)));

        $R = substr($nameAD, 0, strlen($nameAD) - strlen(basename($nameAD)));
        $listado = glob($R.'anx-*'.basename($nameAD).'.*');
        foreach($listado as $elemento){
            $zip->addFile($elemento, basename($elemento));
        }
        $zip->close();
        return $nameZip;
    }

    
    function days_between_dates($date_from, $date_to){
        $date_initial = new DateTime(Carbon::parse($date_from)->format('Y-m-d'));
        $date_final = new DateTime(Carbon::parse($date_to)->format('Y-m-d'));
        $interval = $date_initial->diff($date_final);
        if($interval->invert)
            return $interval->days * (-1);
        else
            return $interval->days;
    }


    //CREANDO EL PDF DE LA FACTURA - INVOICE
    protected function createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $cufecude, $tipodoc = "INVOICE", $withHoldingTaxTotal = NULL, $notes = NULL, $healthfields)
    {
        set_time_limit(0); // sin limite de timepo de ejecucion
        $template_pdf = 2;
        ini_set("pcre.backtrack_limit", "5000000");
        $QRStr = '';

        define("DOMPDF_ENABLE_REMOTE", true);  //DOMPDF_ENABLE_REMOTE true, Permite cargar contenido de cualquier URL (http/https) Habilitando la capacidad de cargar recursos remotos (imágenes, CSS, fuentes) desde URLs externas cuando se genera un PDF.

        //Si se pasa el logo
        if(isset($request->establishment_logo)){ //$request->establishment_logo es la imagen jpg en base64
            $filenameLogo = storage_path("app/public/{$company->identification_number}/alternate_{$company->identification_number}{$company->dv}.jpg"); //obtener la ruta de la imagen jpg
            $this->storeLogo($request->establishment_logo); //almacenar el logo que viene en base 64
        }else{ $filenameLogo   = storage_path("app/public/{$company->identification_number}/{$company->identification_number}{$company->dv}.jpg"); }

        //si existe el contenido del logo
        if(file_exists($filenameLogo)){
            $logoBase64 = base64_encode(file_get_contents($filenameLogo));
            $imgLogo = "data:image/jpg;base64, ".$logoBase64;
        } else{
            $logoBase64 = NULL; $imgLogo = NULL;
        }

        $firma_facturacion = null;

        $totalbase = $request->legal_monetary_totals['line_extension_amount'];
        if($tipodoc == "ND")$totalbase = $request->requested_monetary_totals['line_extension_amount'];

        //CALCULAR QR EN BASE-64 PARA HAB Y PRODUCCION
        if($tipodoc == "INVOICE"){//Si tipo de documento es invoice - Factura
            if($company->type_environment_id == 2){ //si es ambiente de pruebas
                if(isset($request->tax_totals[0]['tax_amount'])){ //si esta definido tax total, se establece el impuesto IVA
                    $qrBase64 = base64_encode(QrCode::format('svg') //me codifica en base 64 el qr del png y establece URL de habilitacion
                                            ->errorCorrection('Q')
                                            ->size(220)
                                            ->margin(0)
                                            ->generate('NumFac: '.$request->number.PHP_EOL.'FecFac: '.$date.PHP_EOL.'NitFac: '.$company->identification_number.PHP_EOL.'DocAdq: '.$customer->company->identification_number.PHP_EOL.'ValFac: '.$request->legal_monetary_totals['tax_exclusive_amount'].PHP_EOL.'ValIva: '.$request->tax_totals[0]['tax_amount'].PHP_EOL.'ValOtroIm: 0.00'.PHP_EOL.'ValTotal: '.$request->legal_monetary_totals['payable_amount'].PHP_EOL.'CUFE: '.$cufecude.PHP_EOL.'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                    $QRStr = 'NumFac: '.$request->number.PHP_EOL.'FecFac: '.$date.PHP_EOL.'NitFac: '.$company->identification_number.PHP_EOL.'DocAdq: '.$customer->company->identification_number.PHP_EOL.'ValFac: '.$request->legal_monetary_totals['tax_exclusive_amount'].PHP_EOL.'ValIva: '.$request->tax_totals[0]['tax_amount'].PHP_EOL.'ValOtroIm: 0.00'.PHP_EOL.'ValTotal: '.$request->legal_monetary_totals['payable_amount'].PHP_EOL.'CUFE: '.$cufecude.PHP_EOL.'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$cufecude;
                }
                else{ //No se establece el impuesto IVA y establece URL de habilitacion
                    $qrBase64 = base64_encode(QrCode::format('svg')
                                            ->errorCorrection('Q')
                                            ->size(220)
                                            ->margin(0)
                                            ->generate('NumFac: '.$request->number.PHP_EOL.'FecFac: '.$date.PHP_EOL.'NitFac: '.$company->identification_number.PHP_EOL.'DocAdq: '.$customer->company->identification_number.PHP_EOL.'ValFac: '.$request->legal_monetary_totals['tax_exclusive_amount'].PHP_EOL.'ValIva: 0.00'.PHP_EOL.'ValOtroIm: 0.00'.PHP_EOL.'ValTotal: '.$request->legal_monetary_totals['payable_amount'].PHP_EOL.'CUFE: '.$cufecude.PHP_EOL.'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                    $QRStr = 'NumFac: '.$request->number.PHP_EOL.'FecFac: '.$date.PHP_EOL.'NitFac: '.$company->identification_number.PHP_EOL.'DocAdq: '.$customer->company->identification_number.PHP_EOL.'ValFac: '.$request->legal_monetary_totals['tax_exclusive_amount'].PHP_EOL.'ValIva: 0.00'.PHP_EOL.'ValOtroIm: 0.00'.PHP_EOL.'ValTotal: '.$request->legal_monetary_totals['payable_amount'].PHP_EOL.'CUFE: '.$cufecude.PHP_EOL.'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$cufecude;
                }
            }else{ // si es produccion
                $qrBase64 = base64_encode(QrCode::format('svg')
                                        ->errorCorrection('Q')
                                        ->size(220)
                                        ->margin(0)
                                        ->generate('https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                $QRStr = 'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude;
            }

            $imageQr    =  "data:image/svg+xml;base64, ".$qrBase64;
            
            $pdf = $this->initMPdf(); //inicializar mpdf para facturas
            $pdf->SetHTMLHeader(View::make("pdfs.".strtolower($tipodoc).".header", compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")));
            $pdf->SetHTMLFooter(View::make("pdfs.".strtolower($tipodoc).".footer", compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")));
            $pdf->WriteHTML(View::make("pdfs.".strtolower($tipodoc).".template".$template_pdf, compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")), HTMLParserMode::HTML_BODY);
            $filename = storage_path("app/public/{$company->identification_number}/FES-{$resolution->next_consecutive}.pdf");  //ubicacion en donde se guardara el PDF.   
        }//fin tipo de documentos INVOICE
        if($tipodoc == "NC"){
            if ($company->type_environment_id == 2){
                $qrBase64 = base64_encode(QrCode::format('svg')
                                        ->errorCorrection('Q')
                                        ->size(220)
                                        ->margin(0)
                                        ->generate('https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                $QRStr = 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$cufecude;  
            }else{ // si es produccion
                $qrBase64 = base64_encode(QrCode::format('svg')
                                    ->errorCorrection('Q')
                                    ->size(220)
                                    ->margin(0)
                                    ->generate('https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                $QRStr = 'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude;
            }
            
            $imageQr    =  "data:image/svg+xml;base64, ".$qrBase64;

            $pdf = $this->initMPdf('credit-note');
            $pdf->SetHTMLHeader(View::make("pdfs.credit-note.header", compact("resolution", "date", "time", "user", "request", "company", "imgLogo")));
            $pdf->SetHTMLFooter(View::make("pdfs.credit-note.footer", compact("resolution", "request", "cufecude", "date", "time")));
            $pdf->WriteHTML(View::make("pdfs.credit-note.template".$template_pdf, compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields")), HTMLParserMode::HTML_BODY);
            $filename = storage_path("app/public/{$company->identification_number}/NCS-{$resolution->next_consecutive}.pdf");
        }//fin nota credito de INVOICE
        if($tipodoc == "SUPPORTDOCUMENT"){
            if($company->type_environment_id == 2){
                $qrBase64 = base64_encode(QrCode::format('svg')
                                        ->errorCorrection('Q')
                                        ->size(220)
                                        ->margin(0)
                                        ->generate('https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                $QRStr = 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$cufecude;
            }else{  // si es produccion
                $qrBase64 = base64_encode(QrCode::format('svg')
                                        ->errorCorrection('Q')
                                        ->size(220)
                                        ->margin(0)
                                        ->generate('https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                $QRStr = 'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude;
            }
            $imageQr    =  "data:image/svg+xml;base64, ".$qrBase64;

            $pdf = $this->initMPdf();
            $pdf->SetHTMLHeader(View::make("pdfs.support.header", compact("resolution", "date", "time", "user", "request", "company", "imgLogo")));
            $pdf->SetHTMLFooter(View::make("pdfs.support.footer", compact("resolution", "request", "cufecude", "date", "time")));
            $pdf->WriteHTML(View::make("pdfs.support.template".$template_pdf, compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields")), HTMLParserMode::HTML_BODY);
            $filename = storage_path("app/public/{$company->identification_number}/DSS-{$resolution->next_consecutive}.pdf");
        }//fin DOCUMENTO SOPORTE

        $pdf->Output($filename); //guardar PDF en disco
        return $QRStr; //retorna QR
    }


    //INICIALIZANDO LIBREARIA MPDF
    protected function initMPdf(string $type = 'invoice'): Mpdf
    { //$type: tipo de documento (invoice, pos),     $template: numero/idenificador de plantilla es opcional
        //Configuración Inicial de Fuentes, Obtiene configuraciones predeterminadas de mPDF
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        //Dimensiones de Página, Define tamaños para facturas
        $pageWidth = 219;   // Ancho carta en mm
        $pageHeight = 279;  // Alto carta en mm

        //inicializar mpdf
        $pdf = new Mpdf([
            'fontDir' => array_merge($fontDirs, [base_path('public/fonts/roboto/'),]),
            'fontdata' => $fontData + [
                'Roboto' => [
                    'R' => 'Roboto-Regular.ttf',
                    'B' => 'Roboto-Bold.ttf',
                    'I' => 'Roboto-Italic.ttf',
                ]
            ],
            'default_font' => 'Roboto',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 35,
            'margin_bottom' => 5,
            'margin_header' => 5,
            'margin_footer' => 2,
            'format' => [$pageWidth, $pageHeight], // Establece el tamaño de la página
        ]);
        
        $pdf->WriteHTML(file_get_contents(base_path('resources/views/pdfs/'.$type.'/styles.css')), HTMLParserMode::HEADER_CSS);
        return $pdf;
    }


    protected function ValueXML($stringXML, $xpath){
        if(substr($xpath, 0, 1) != '/')
            return NULL;
        $search = substr($xpath, 1, strpos(substr($xpath, 1), '/'));
        $posinicio = strpos($stringXML, "<".$search);
        if($posinicio == 0 and $search != 's:Envelope')
           return NULL;
        $posinicio = strpos($stringXML, ">", $posinicio) + 1;
        $posCierre = strpos($stringXML, "</".$search.">", $posinicio);
        if($posCierre == 0)
            return NULL;
        $valorXML = substr($stringXML, $posinicio, $posCierre - $posinicio);
        if(strcmp(substr($xpath, strpos($xpath, $search) + strlen($search)), '/') != 0)
            return $this->ValueXML($valorXML, substr($xpath, strpos($xpath, $search) + strlen($search)));
        else
            return $valorXML;
    }
    
}