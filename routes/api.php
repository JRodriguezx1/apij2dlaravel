<?php

//use App\Http\Controllers\ConfigurationController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConfigurationController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\SupportDocumentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/*Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});*/


Route::get('/getconfigcompanies', [ConfigurationController::class, 'index']);
//route::apiResource()  Route::apiResource() ya incluye internamente todos los verbos HTTP necesarios para una API RESTful GET	/api/productos	index, POST	/api/productos	store, GET	/api/productos/{id}	show, PUT/PATCH	/api/productos/{id}	update, DELETE	/api/productos/{id}	destroy


Route::prefix('/ubl2.1')->group(function(){
    //configuracion
    Route::prefix('/config')->group(function(){
        Route::post('{nit}/{dv?}', [ConfigurationController::class, 'store']); //crear configurar compañia
    });
});

Route::middleware('auth.token')->group(function(){
    Route::prefix('/ubl2.1')->group(function(){
        //configuracion
        Route::prefix('/config')->group(function(){
            Route::put('/software', [ConfigurationController::class, 'storeSoftware']);
            Route::put('/certificate', [ConfigurationController::class, 'storeCertificate']);
            Route::put('/resolution', [ConfigurationController::class, 'storeResolution']);
            Route::put('/environment', [ConfigurationController::class, 'storeEnvironment']);
        });

        //Invoice
        Route::prefix('/invoice')->group(function(){
            Route::post('/{testSetId}', [InvoiceController::class, 'testSetStore']);  //enviar una factura en modo habilitacion
            Route::post('/', [InvoiceController::class, 'store']); //enviar una factura electronica
        });

        //nota credito
        Route::prefix('/credit-note')->group(function(){
            Route::post('/{testSetId}', [CreditNoteController::class, 'testSetStore']);  //
            Route::post('/', [CreditNoteController::class, 'store']); //
        });

        Route::prefix('/support-document')->group(function(){
            Route::post('/{testSetId}', [SupportDocumentController::class, 'testSetStore']);  //enviar un documento soporte en modo habilitacion
            Route::post('/', [SupportDocumentController::class, 'store']); //enviar un documento soporte
        });

        //Status
        Route::prefix('/status')->group(function(){
            Route::post('/zip/{trackId}/{GuardarEn?}', [StateController::class, 'zip']);  //validar el estado del zip de una factura enviada
            Route::post('/document/{trackId}/{GuardarEn?}', [StateController::class, 'document']);
        });
    });
});