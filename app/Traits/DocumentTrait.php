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

    protected function getTag($document, $tagName, $item = 0, $attribute = NULL, $attribute_value = NULL)
    {
        if (is_string($document)){
            $xml = $document;
            $document = new \DOMDocument;
            $document->loadXML($xml);
        }

        $tag = $document->documentElement->getElementsByTagName($tagName);

        if (is_null($tag->item(0))) {
            return;
        }

        if($attribute)
            if($attribute_value){
                $tag->item($item)->setAttribute($attribute, $attribute_value);
                return;
            }
            else
                return $tag->item($item)->getAttribute($attribute);
        else
            return $tag->item($item);
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
                if($resta != 0)
                    return 11 - $resta;
                else
                    return $resta;
        } else {
            return FALSE;
        }
    }


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
    
    
    protected function createXML(array $data)
    {
        if($data['typeDocument']['code'] === '01' or $data['typeDocument']['code'] === '02' or $data['typeDocument']['code'] === '03' or $data['typeDocument']['code'] === '05' or $data['typeDocument']['code'] === '95' or $data['typeDocument']['code'] === '91' or $data['typeDocument']['code'] === '92' or $data['typeDocument']['code'] === '20' or $data['typeDocument']['code'] === '93' or $data['typeDocument']['code'] === '94'){
            if($data['typeDocument']['code'] === '20' or $data['typeDocument']['code'] === '93' or $data['typeDocument']['code'] === '94'){
                if($data['company']['eqdocs_type_environment_id'] == 2)
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


    function days_between_dates($date_from, $date_to){
        $date_initial = new DateTime(Carbon::parse($date_from)->format('Y-m-d'));
        $date_final = new DateTime(Carbon::parse($date_to)->format('Y-m-d'));
        $interval = $date_initial->diff($date_final);
        if($interval->invert)
            return $interval->days * (-1);
        else
            return $interval->days;
    }


    protected function createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $cufecude, $tipodoc = "INVOICE", $withHoldingTaxTotal = NULL, $notes = NULL, $healthfields)
    {
        $template_json = false;
        set_time_limit(0); // sin limite de timepo de ejecucion
        //si hay plantilla indicada
        if(isset($request->invoice_template))
          if(!is_null($request->invoice_template) && ($request->invoice_template <> '')){
            if(password_verify($company->identification_number, $request->template_token)){
                $template_pdf = $request->invoice_template;
                $template_json = true;
            }
            else
                $template_pdf = env("GRAPHIC_REPRESENTATION_TEMPLATE", 2);
          }
          else
            $template_pdf = env("GRAPHIC_REPRESENTATION_TEMPLATE", 2);
        else  //si no hay plantilla personalida $template_pdf toma el valor de "GRAPHIC_REPRESENTATION_TEMPLATE" o 2
          $template_pdf = env("GRAPHIC_REPRESENTATION_TEMPLATE", 2);
        ini_set("pcre.backtrack_limit", "5000000");
        $QRStr = '';

        define("DOMPDF_ENABLE_REMOTE", true);

        //Si se pasa el logo
        if(isset($request->establishment_logo)){
            $filenameLogo   = storage_path("app/public/{$company->identification_number}/alternate_{$company->identification_number}{$company->dv}.jpg");
            $this->storeLogo($request->establishment_logo);
        }
        else
            $filenameLogo   = storage_path("app/public/{$company->identification_number}/{$company->identification_number}{$company->dv}.jpg");

        $firma_facturacion = null;
        if(isset($request->firma_facturacion)){
            $firma_facturacion = "data:image/jpg;base64, ".$request->firma_facturacion;
        }

        //si existe el contenido del logo
        if(file_exists($filenameLogo)){
            $logoBase64     = base64_encode(file_get_contents($filenameLogo));
            $imgLogo        = "data:image/jpg;base64, ".$logoBase64;
        } else {
            $logoBase64     = NULL;
            $imgLogo        = NULL;
        }

        if($tipodoc == "ND")
            $totalbase = $request->requested_monetary_totals['line_extension_amount'];
        else
            $totalbase = $request->legal_monetary_totals['line_extension_amount'];

        //CALCULAR QR EN BASE-64 PARA HAB Y PRODUCCION
        if($tipodoc == "INVOICE" || $tipodoc == "POS"){//Si tipo de documento es invoice o POS
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
            }
            else{ // si es produccion
                if(isset($request->tax_totals[0]['tax_amount'])){ //si esta definido tax total
                    $qrBase64 = base64_encode(QrCode::format('svg') //se establece el impuesto IVA y URL de produccion, QR codificado en base 64
                                            ->errorCorrection('Q')
                                            ->size(220)
                                            ->margin(0)
                                            ->generate('NumFac: '.$request->number.PHP_EOL.'FecFac: '.$date.PHP_EOL.'NitFac: '.$company->identification_number.PHP_EOL.'DocAdq: '.$customer->company->identification_number.PHP_EOL.'ValFac: '.$request->legal_monetary_totals['tax_exclusive_amount'].PHP_EOL.'ValIva: '.$request->tax_totals[0]['tax_amount'].PHP_EOL.'ValOtroIm: 0.00'.PHP_EOL.'ValTotal: '.$request->legal_monetary_totals['payable_amount'].PHP_EOL.'CUFE: '.$cufecude.PHP_EOL.'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                    $QRStr = 'NumFac: '.$request->number.PHP_EOL.'FecFac: '.$date.PHP_EOL.'NitFac: '.$company->identification_number.PHP_EOL.'DocAdq: '.$customer->company->identification_number.PHP_EOL.'ValFac: '.$request->legal_monetary_totals['tax_exclusive_amount'].PHP_EOL.'ValIva: '.$request->tax_totals[0]['tax_amount'].PHP_EOL.'ValOtroIm: 0.00'.PHP_EOL.'ValTotal: '.$request->legal_monetary_totals['payable_amount'].PHP_EOL.'CUFE: '.$cufecude.PHP_EOL.'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude;
                }
                else{ //No se establece el impuesto IVA y establece URL de produccion
                    $qrBase64 = base64_encode(QrCode::format('svg')
                                            ->errorCorrection('Q')
                                            ->size(220)
                                            ->margin(0)
                                            ->generate('NumFac: '.$request->number.PHP_EOL.'FecFac: '.$date.PHP_EOL.'NitFac: '.$company->identification_number.PHP_EOL.'DocAdq: '.$customer->company->identification_number.PHP_EOL.'ValFac: '.$request->legal_monetary_totals['tax_exclusive_amount'].PHP_EOL.'ValIva: 0.00'.PHP_EOL.'ValOtroIm: 0.00'.PHP_EOL.'ValTotal: '.$request->legal_monetary_totals['payable_amount'].PHP_EOL.'CUFE: '.$cufecude.PHP_EOL.'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude));
                    $QRStr = 'NumFac: '.$request->number.PHP_EOL.'FecFac: '.$date.PHP_EOL.'NitFac: '.$company->identification_number.PHP_EOL.'DocAdq: '.$customer->company->identification_number.PHP_EOL.'ValFac: '.$request->legal_monetary_totals['tax_exclusive_amount'].PHP_EOL.'ValIva: 0.00'.PHP_EOL.'ValOtroIm: 0.00'.PHP_EOL.'ValTotal: '.$request->legal_monetary_totals['payable_amount'].PHP_EOL.'CUFE: '.$cufecude.PHP_EOL.'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$cufecude;
                }
            }

             $imageQr    =  "data:image/svg+xml;base64, ".$qrBase64;

            if($template_json){
                    if($tipodoc == 'POS')
                        $pdf = $this->initMPdf('pos', $template_pdf);
                    else
                        $pdf = $this->initMPdf('invoice', $template_pdf);
                    $pdf->SetHTMLHeader(View::make("pdfs.".strtolower($tipodoc).".header".$template_pdf, compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")));
                    $pdf->SetHTMLFooter(View::make("pdfs.".strtolower($tipodoc).".footer".$template_pdf, compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")));
                    $pdf->WriteHTML(View::make("pdfs.".strtolower($tipodoc).".template".$template_pdf, compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")), HTMLParserMode::HTML_BODY);
            }else{
                if($tipodoc == 'POS')
                    $pdf = $this->initMPdf('pos', $template_pdf);
                else
                    $pdf = $this->initMPdf(); //inicializar mpdf para facturas
                    $pdf->SetHTMLHeader(View::make("pdfs.".strtolower($tipodoc).".header", compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")));
                    $pdf->SetHTMLFooter(View::make("pdfs.".strtolower($tipodoc).".footer", compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")));
                    $pdf->WriteHTML(View::make("pdfs.".strtolower($tipodoc).".template".$template_pdf, compact("user", "company", "customer", "resolution", "date", "time", "paymentForm", "request", "cufecude", "imageQr", "imgLogo", "withHoldingTaxTotal", "notes", "healthfields", "firma_facturacion")), HTMLParserMode::HTML_BODY);
            }

            $filename = storage_path("app/public/{$company->identification_number}/FES-{$resolution->next_consecutive}.pdf");  //ubicacion en donde se guardara el PDF.
             
        }//fin tipo de documentos INVOICE o POS

        $pdf->Output($filename);
        return $QRStr;

    }


    protected function initMPdf(string $type = 'invoice', string $template = null): Mpdf
    //$type: tipo de documento (invoice, pos),     $template: numero/idenificador de plantilla es opcional
    {
        //Configuración Inicial de Fuentes, Obtiene configuraciones predeterminadas de mPDF
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        //Dimensiones de Página, Define tamaños diferentes para facturas vs tickets POS
        if($type == 'pos'){
            $pageWidth = 80;    // Ancho para POS (tickets)
            $pageHeight = 297;  // Largo estándar para rollos
        }
        else{
            $pageWidth = 219;   // Ancho carta en mm
            $pageHeight = 279;  // Alto carta en mm
        }

        //Configuración de Márgenes
        $margin_left = '5';
        $margin_right = '5';
        $margin_top = '60';
        $margin_bottom = '40';

        //Carga de Configuración Personalizada (JSON) si existe deacuerdo a variable $template
        $filename = base_path('resources/views/pdfs/' . $type . '/config'.$template.'.json');
        if (file_exists($filename)) {
            $jsonD =  file_get_contents('config'.$template.'.json');
            $margin = json_decode($jsonD, true);
            if(isset($margin)){
                $margin_top = $margin['top'];
                $margin_right = $margin['right'];
                $margin_bottom = $margin['bottom'];
                $margin_left = $margin['left'];
            }
        }
        if($template){
            $pdf = new Mpdf([
                'fontDir' => array_merge($fontDirs, [base_path('public/fonts/roboto/')]),
                'fontdata' => $fontData + [
                    'Roboto' => [
                        'R' => 'Roboto-Regular.ttf',
                        'B' => 'Roboto-Bold.ttf',
                        'I' => 'Roboto-Italic.ttf',
                    ]
                ],
                'default_font' => 'Roboto',
                'margin_left' => $margin_left,
                'margin_right' => $margin_right,
                'margin_top' => $margin_top,
                'margin_bottom' => $margin_bottom ,
                'margin_header' => 5,
                'margin_footer' => 2,
                'format' => [$pageWidth, $pageHeight], // Establece el tamaño de la página
            ]);
        }else{  //si no plantilla enviada
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
                'margin_top' => 35,
                'margin_bottom' => 5,
                'margin_header' => 5,
                'margin_footer' => 2,
                'format' => [$pageWidth, $pageHeight], // Establece el tamaño de la página
            ]);
        }
        if($template)
            $pdf->WriteHTML(file_get_contents(base_path('resources/views/pdfs/' . $type . '/styles'.$template.'.css')), HTMLParserMode::HEADER_CSS);
        else
            $pdf->WriteHTML(file_get_contents(base_path('resources/views/pdfs/' . $type . '/styles.css')), HTMLParserMode::HEADER_CSS);
        return $pdf;
    }
    
}