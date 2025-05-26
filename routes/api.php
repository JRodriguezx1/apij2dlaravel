<?php

//use App\Http\Controllers\ConfigurationController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConfigurationController;
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

        /*Route::put('/software', [ConfigurationController::class, 'storeSoftware']);
        Route::put('/softwarepayroll', [ConfigurationController::class, 'storeSoftware']);
        Route::put('/softwareeqdocs', [ConfigurationController::class, 'storeSoftware']);
        Route::put('/certificate', [ConfigurationController::class, 'storeCertificate']);
        Route::put('/resolution', [ConfigurationController::class, 'storeResolution']);
        Route::put('/environment', [ConfigurationController::class, 'storeEnvironment']);
        Route::put('/logo', [ConfigurationController::class, 'storeLogo']);
        Route::put('/generateddocuments', [ConfigurationController::class, 'storeInitialDocument']);*/
    });
});

Route::middleware('auth.token')->group(function(){
    Route::prefix('/ubl2.1')->group(function(){
        //configuracion
        Route::prefix('/config')->group(function(){
            Route::put('/software', [ConfigurationController::class, 'storeSoftware']);
        });
    });
});