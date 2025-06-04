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
use Illuminate\Support\Facades\Storage;


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
    public function store(Request $request)
    {
        //
        return "hola store";
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
        }
        else{
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
            $idcurrency = TypeCurrency::findOrFail(33/*$invoice_doc->currency_id*/);
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
        return [
                'success' => true,
                'message' => 'Invoice ok',
                'content' => htmlentities($invoice->saveXML())
            ];
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        //
    }
}
