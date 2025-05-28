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

    /**
     * Show the form for editing the specified resource.
     */
    public function storeCertificate(ConfigurationCertificateRequest $request):array
    {
        if (!base64_decode($request->certificate, true)) {
                throw new Exception('The given data was invalid.');
            }
           
        if (!openssl_pkcs12_read($certificateBinary = base64_decode($request->certificate), $certificate, $request->password)) {
            throw new Exception('The certificate could not be read.');
        }
        return [];
    }

    /**
     * Update the specified resource in storage.
     */
    public function storeResolution(ConfigurationResolutionRequest $request)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function storeEnvironment(ConfigurationEnvironmentRequest $request)
    {
        //
    }
}
