<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfigurationCertificateRequest;
use App\Http\Requests\Api\ConfigurationEnvironmentRequest;
use Illuminate\Http\Request;
use App\Http\Requests\Api\ConfigurationRequest;
use App\Http\Requests\Api\ConfigurationResolutionRequest;
use App\Http\Requests\Api\ConfigurationSoftwareRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Company;
use App\Models\Software;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Storage;

class ConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $company = Company::all();
        return response()->json($company);
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
    public function store(ConfigurationRequest $request, $nit, $dv = null)
    {
        /*DB::transaction();
        try {
            
        } catch (\Throwable $th) {
            //throw $th;
        }*/
        /*return response()->json([
        'all' => $request->all(),
        'method' => $request->method()
        ]);*/
        $password = $nit;
        $existe = Company::where('identification_number', $nit)->get();  // get retorna una coleccion
  
        if($existe->isEmpty()){  //CREAR USUARIO DE LA COMPAÑIA
            $user = User::create([
                'name' => $request->business_name,
                'email' => $request->email,
                'password' => bcrypt($password),
                'id_administrador' => $request->id_administrator ?? 1,
                'mail_host' => $request->mail_host,
                'mail_port' => $request->mail_port,
                'mail_username' => $request->mail_username,
                'mail_password' => $request->mail_password,
                'mail_encryption' => $request->mail_encryption,
                'api_token' => hash('sha256', $password) //GENERA TOKEN
                ]);

            $user->api_token = hash('sha256', $password); //GENERA TOKEN
            $user->save();

            if(isset($request->type_plan_id))
                    $start_plan_date = Carbon::now()->format('Y-m-d H:i');
                else
                    $start_plan_date = NULL;

                if(isset($request->type_plan2_id))
                    $start_plan_date2 = Carbon::now()->format('Y-m-d H:i');
                else
                    $start_plan_date2 = NULL;

                if(isset($request->type_plan3_id))
                    $start_plan_date3 = Carbon::now()->format('Y-m-d H:i');
                else
                    $start_plan_date3 = NULL;

                if(isset($request->type_plan4_id))
                    $start_plan_date4 = Carbon::now()->format('Y-m-d H:i');
                else
                    $start_plan_date4 = NULL;

                if(isset($request->absolut_plan_documents))
                    $absolut_start_plan_date = Carbon::now()->format('Y-m-d H:i');
                else
                    $absolut_start_plan_date = NULL;

            $compañia = $user->company()->create([  //CREAR COMPAÑIA
                'user_id' => $user->id,
                'identification_number' => $nit,
                'dv' => $dv,
                'language_id' => $request->language_id ?? 79,
                'tax_id' => $request->tax_id ?? 1,
                'type_environment_id' => $request->type_environment_id ?? 2,
                'payroll_type_environment_id' => $request->payroll_type_environment_id ?? 2,
                'eqdocs_type_environment_id' => $request->eqdocs_type_environment_id ?? 2,
                'type_operation_id' => $request->type_operation_id ?? 10,
                'type_document_identification_id' => $request->type_document_identification_id,
                'country_id' => $request->country_id ?? 46,
                'type_currency_id' => $request->type_currency_id ?? 35,
                'type_organization_id' => $request->type_organization_id,
                'type_regime_id' => $request->type_regime_id,
                'type_liability_id' => $request->type_liability_id,
                'municipality_id' => $request->municipality_id,
                'merchant_registration' => $request->merchant_registration,
                'address' => $request->address,
                'phone' => $request->phone,
                'type_plan_id' => $request->type_plan_id ?? 0,
                'type_plan2_id' => $request->type_plan2_id ?? 0,
                'type_plan3_id' => $request->type_plan3_id ?? 0,
                'type_plan4_id' => $request->type_plan4_id ?? 0,
                'absolut_plan_documents' => $request->absolut_plan_documents,
                'state' => $request->state ?? 1,
                'start_plan_date' => $start_plan_date,
                'start_plan_date2' => $start_plan_date2,
                'start_plan_date3' => $start_plan_date3,
                'start_plan_date4' => $start_plan_date4,
                'absolut_start_plan_date' => $absolut_start_plan_date,
                ]);

            //$user->save();

        }else{  //ACTUALIZAR COMPAÑIA
            return 'actualizar';
        }

        if($user && $compañia){
             return [
                'success' => true,
                'message' => 'Empresa creada/actualizada con éxito',
                'password' => $user->password,
                'token' => $user->api_token,
                'company' => $user->company,
            ];
        }
        
    }

    /**
     * Display the specified resource.
     */
    public function storeSoftware(ConfigurationSoftwareRequest $request):array
    {
        //$user = auth()->user()->company;  //obtengo usuario autenticado y luego su compañia

        $s = auth()->user()->company->software;  //obtengo usuario autenticado, luego su compañia y despues su software
            if(is_null($s)){  //si no hay software lo crea
                $software = auth()->user()->company->software()->create(
                    [
                    'identifier' => $request->id ?? '',
                    'pin' => $request->pin ?? '',
                    'url' => $request->url ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                    'url_payroll' => $request->urlpayroll ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                    'identifier_payroll' => $request->idpayroll ?? '',
                    'pin_payroll' => $request->pinpayroll ?? '',
                    //'url_sd' => $request->urlsd ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                    //'identifier_sd' => $request->idsd ?? '',
                    //'pin_sd' => $request->pinsd ?? '',
                    'url_eqdocs' => $request->urleqdocs ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                    'identifier_eqdocs' => $request->ideqdocs ?? '',
                    'pin_eqdocs' => $request->pineqdocs ?? '',
                    ]
                );
            }else{
                $software = auth()->user()->company->software()->update(
                    [
                    'identifier' => $request->id ?? $s->identifier,
                    'pin' => $request->pin ?? $s->pin,
                    'url' => $request->url ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                    'url_payroll' => $request->urlpayroll ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                    'identifier_payroll' => $request->idpayroll ?? $s->identifier_payroll,
                    'pin_payroll' => $request->pinpayroll ?? $s->pin_payroll,
                    //'url_sd' => $request->urlsd ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                    //'identifier_sd' => $request->idsd ?? $s->identifier_sd,
                    //'pin_sd' => $request->pinsd ?? $s->pin_sd,
                    'url_eqdocs' => $request->urleqdocs ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                    'identifier_eqdocs' => $request->ideqdocs ?? '',
                    'pin_eqdocs' => $request->pineqdocs ?? '',
                    ]
                );
            }

            $s = Software::where('company_id', auth()->user()->company->id)->firstOrFail(); //obtenemos el software segun el id de la compañia
            return [
                'success' => true,
                'message' => 'Software creado/actualizado con éxito',
                'software' => $s,
            ];
        
    }


    public function CertificateEndDate($user = FALSE){
        if($user === FALSE)
            $company = auth()->user()->company;
        else
            $company = $user->company;
        $pfxContent = file_get_contents(storage_path("app/certificates/".$company->certificate->name));
        try {
            if (!openssl_pkcs12_read($pfxContent, $x509certdata, $company->certificate->password)) {
                throw new Exception('The certificate could not be read.');
            }
            else{
                $CertPriv   = array();
                $CertPriv   = openssl_x509_parse(openssl_x509_read($x509certdata['cert']));

                $PrivateKey = $x509certdata['pkey'];

                $pub_key = openssl_pkey_get_public($x509certdata['cert']);
                $keyData = openssl_pkey_get_details($pub_key);

                $PublicKey  = $keyData['key'];

                //return $CertPriv['name'];                                     //Nome
                //return $CertPriv['hash'];                                     //hash
                //return $CertPriv['subject']['C'];                             //País
                //return $CertPriv['subject']['ST'];                            //Estado
                //return $CertPriv['subject']['L'];                             //Município
                //return $CertPriv['subject']['CN'];                            //Razão Social e CNPJ / CPF
                return date('d/m/Y', $CertPriv['validTo_time_t'] );             //Validade
                //return $CertPriv['extensions']['subjectAltName'];             //Emails Cadastrados separado por ,
                //return $CertPriv['extensions']['authorityKeyIdentifier'];
                //return $CertPriv['issuer'];                                   //Emissor
                //return $PublicKey;
                //return $PrivateKey;
            }
        } catch (Exception $e) {
            if (false == ($error = openssl_error_string())) {
                return response([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'certificate' => 'The base64 encoding is not valid.',
                    ],
                ], 422);
            }
            return $pfxContent;
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function storeCertificate(ConfigurationCertificateRequest $request)
    {
        try{
            if(!base64_decode($request->certificate, true)){
                throw new Exception('The given data was invalid.');
            }
            
            if(!openssl_pkcs12_read($certificateBinary = base64_decode($request->certificate), $certificate, $request->password)){
                throw new Exception('The certificate could not be read.');
            }
        }catch(Exception $e){
            if(false == ($error = openssl_error_string())){
                return response([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'certificate' => 'The base64 encoding is not valid.',
                    ],
                ], 422);
            }

            return response([
                'message' => $e->getMessage(),
                'errors' => [
                    'certificate' => $error,
                    'password' => $error,
                ],
            ], 422);
        }

        auth()->user()->company->certificate()->delete();  //obtengo el usuario autenticado, luiego su compañia despues el certificado asociado y elimino
        $company = auth()->user()->company;  //obtengo la compañia del usuario
        $name = "{$company->identification_number}{$company->dv}.p12";  //nombre del certificado = {nit+dv}.p12
        Storage::put("certificates/{$name}", $certificateBinary);  //se guarda el certificado en base 64 en local: storage/app/certificates/nombre.p12

        $pfxContent = file_get_contents(storage_path("app/certificates/".$name));  //lee el archivo local recien guardado
        if(!openssl_pkcs12_read($pfxContent, $x509certdata, $request->password)){  //si no se puede leer el archivo, entra y muestra error
            throw new Exception('The certificate could not be read.');
        }
        else{
            $CertPriv   = array();
            $CertPriv   = openssl_x509_parse(openssl_x509_read($x509certdata['cert']));
            $PrivateKey = $x509certdata['pkey'];
            $pub_key = openssl_pkey_get_public($x509certdata['cert']);
            $keyData = openssl_pkey_get_details($pub_key);
            $PublicKey  = $keyData['key'];
            $expiration_date = date('Y/m/d H:i:s', $CertPriv['validTo_time_t']);
        }

        $certificate = auth()->user()->company->certificate()->create([
            'name'=>$name,
            'password'=>$request->password,
            'expiration_date'=>$expiration_date
        ]);
        return [
                'success' => true,
                'message' => 'Certificado creado con éxito',
                'certificado' => $certificate,
            ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function storeResolution(ConfigurationResolutionRequest $request)
    {
        if($request->delete_all_type_resolutions){ //si delete_all_type_resolutions es true se elimina las resoluciones que tengan el mismo tipo de documento como prefix: fv, factura electronica de venta
                $resolution = auth()->user()->company->resolutions()->where('type_document_id', $request->type_document_id)->get();  //obtengo las resoluciones con el mismo tipo de docuemtno prefijo fv
                if(count($resolution) > 0)
                    foreach($resolution as $r)
                        $r->delete();  //eliminio las resoluciones con el mismo prefijo o tipo de factura fv
        }

        $resolution = auth()->user()->company->resolutions()->updateOrCreate([  // si cumple las tres condiciones, tipo de documento, resolution y prefix actualiza de lo contrario crea nuevo registro
                'type_document_id' => $request->type_document_id,
                'resolution' => $request->resolution,
                'prefix' => $request->prefix,
            ], [
                'resolution_date' => $request->resolution_date,
                'technical_key' => $request->technical_key,  //verificar si se puede obtener con numbering-range
                'from' => $request->from,
                'to' => $request->to,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ]);

        return [
                'success' => true,
                'message' => 'Resolución creada/actualizada con éxito',
                'resolution' => $resolution,
            ];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function storeEnvironment(ConfigurationEnvironmentRequest $request)
    {
        //
        if(!$request->type_environment_id)
            $request->type_environment_id = auth()->user()->company->type_environment_id;
        if(!$request->payroll_type_environment_id)
            $request->payroll_type_environment_id = auth()->user()->company->payroll_type_environment_id;
        if(!$request->eqdocs_type_environment_id)
            $request->eqdocs_type_environment_id = auth()->user()->company->eqdocs_type_environment_id;

        //Actualizar COMPAÑIA, campo type_environment_id, .. de la COMPAÑIA 
        auth()->user()->company->update([
            'type_environment_id' => $request->type_environment_id,
            'payroll_type_environment_id' => $request->payroll_type_environment_id,
            'eqdocs_type_environment_id' => $request->eqdocs_type_environment_id,
        ]);

        if ($request->type_environment_id)
            if ($request->type_environment_id == 1)
              auth()->user()->company->software->update(['url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc',]);
            else
               auth()->user()->company->software->update(['url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',]);

        if ($request->payroll_type_environment_id)
            if ($request->payroll_type_environment_id == 1)
              auth()->user()->company->software->update(['url_payroll' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc',]);
            else
               auth()->user()->company->software->update(['url_payroll' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',]);

        if ($request->eqdocs_type_environment_id)
            if ($request->eqdocs_type_environment_id == 1)
              auth()->user()->company->software->update(['url_eqdocs' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc',]);
            else
               auth()->user()->company->software->update(['url_eqdocs' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',]);
    
        return ['message' => 'Ambiente actualizado con éxito', 'company' => auth()->user()->company];
    }
    
}
