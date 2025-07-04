<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width"/>
    {{-- <title>NOTA CREDITO ELECTRONICA Nro: {{$resolution->prefix}} - {{$request->number}}</title> --}}
</head>

<body margin-top:50px>
    @if(isset($request->head_note))
    <div>
        <br>
        <table class="table table-bordered table-condensed table-striped table-responsive" style="margin-top: 10px;">
            <thead>
                <tr><th class="text-center" style="font-size: 10px;">{{$request->head_note}}</th></tr>
            </thead>
        </table>
    </div>
    @endif
    <table style="width: 100%;">
        <tbody>
            <tr>
                <td style="width: 50%">
                    <table>
                        <tr>
                            <td>CC o NIT:</td>
                            <td>{{$customer->company->identification_number}}-{{$request->customer['dv'] ?? NULL}} </td>
                        </tr>
                        <tr>
                            <td>Cliente:</td>
                            <td>{{$customer->name}}</td>
                        </tr>
                        <tr>
                            <td>Régimen:</td>
                            <td>{{$customer->company->type_regime->name}}</td>
                        </tr>
                        <tr>
                            <td>Obligación:</td>
                            <td>{{$customer->company->type_liability->name}}</td>
                        </tr>
                        <tr>
                            <td>Dirección:</td>
                            <td>{{$customer->company->address}}</td>
                        </tr>
                        <tr>
                            <td>Ciudad:</td>
                            @if($customer->company->country->id == 46)
                                <td>{{$customer->company->municipality->name}} - {{$customer->company->country->name}} </td>
                            @else
                                <td>{{$customer->company->municipality_name}} - {{$customer->company->state_name}} - {{$customer->company->country->name}} </td>
                            @endif
                        </tr>
                        <tr>
                            <td>Teléfono:</td>
                            <td>{{$customer->company->phone}}</td>
                        </tr>
                        <tr>
                            <td>Email:</td>
                            <td>{{$customer->email}}</td>
                        </tr>
                    </table>
                </td>

                <td class="vertical-align-top" style="width: 40%; padding-left: 1rem">
                    <table>
                        @if(isset($request['order_reference']['id_order']))
                        <tr>
                            <td>Número Pedido:</td>
                            <td>{{$request['order_reference']['id_order']}}</td>
                        </tr>
                        @endif
                        @if(isset($request['order_reference']['issue_date_order']))
                        <tr>
                            <td>Fecha Pedido:</td>
                            <td>{{$request['order_reference']['issue_date_order']}}</td>
                        </tr>
                        @endif
                        @if(isset($healthfields))
                        <tr>
                            <td>Inicio Periodo Facturación:</td>
                            <td>{{$healthfields->invoice_period_start_date}}</td>
                        </tr>
                        <tr>
                            <td>Fin Periodo Facturación:</td>
                            <td>{{$healthfields->invoice_period_end_date}}</td>
                        </tr>
                        @endif
                    </table>
                </td>

                <td class="vertical-align-top" style="width: 30%; text-align: right">
                    <img style="width: 150px;" src="{{$imageQr}}">
                </td>
            </tr>
        </tbody>
    </table>
    <br>

    <?php $billing_reference = json_decode(json_encode($request->billing_reference)) ?>
    @isset($billing_reference)
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th class="text-center">
                        <p><strong>Referencia: {{$billing_reference->number}} - Fecha: {{$billing_reference->issue_date}}<br/>CUFE: {{$billing_reference->uuid}}</strong></p>
                    </th>
                </tr>
            </thead>
        </table>
        <br>
    @endif

    @isset($healthfields)
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th class="text-center" style="width: 100%;">INFORMACION REFERENCIAL SECTOR SALUD</th>
                </tr>
            </thead>
        </table>
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th class="text-center" style="width: 12%;">Cod Prestador</th>
                    <th class="text-center" style="width: 25%;">Datos Usuario</th>
                    <th class="text-center" style="width: 25%;">Info. Contrat./Cobertura</th>
                    <th class="text-center" style="width: 20%;">Nros. Autoriz./MIPRES</th>
                    <th class="text-center" style="width: 18%;">Info. de Pagos</th>
                </tr>
            </thead>
            <tbody>
                @foreach($healthfields->users_info as $item)
                    <tr>
                        <td>
                            <p style="font-size: 8px">{{$item->provider_code}}</p>
                        </td>
                        <td>
                            <p style="font-size: 8px">Nro ID: {{$item->identification_number}}</p>
                            <p style="font-size: 8px">Nombre: {{$item->first_name}} {{$item->surname}}</p>
                            <p style="font-size: 8px">Tipo Documento: {{$item->health_type_document_identification()->name}}</p>
                            <p style="font-size: 8px">Tipo Usuario: {{$item->health_type_user()->name}}</p>
                        </td>
                        <td>
                            <p style="font-size: 8px">Modalidad Contratación: {{$item->health_contracting_payment_method()->name}}</p>
                            <p style="font-size: 8px">Nro. Contrato: {{$item->contract_number}}</p>
                            <p style="font-size: 8px">Cobertura: {{$item->health_coverage()->name}}</p>
                        </td>
                        <td>
                            <p style="font-size: 8px">Nros Autorización: {{$item->autorization_numbers}}</p>
                            <p style="font-size: 8px">Nro MIPRES: {{$item->mipres}}</p>
                            <p style="font-size: 8px">Entrega MIPRES: {{$item->mipres_delivery}}</p>
                            <p style="font-size: 8px">Nro Poliza: {{$item->policy_number}}</p>
                        </td>
                        <td>
                            <p style="font-size: 8px">Copago: {{number_format($item->co_payment, 2)}}</p>
                            <p style="font-size: 8px">Cuota Moderardora: {{number_format($item->moderating_fee, 2)}}</p>
                            <p style="font-size: 8px">Cuota Recuperación: {{number_format($item->recovery_fee, 2)}}</p>
                            <p style="font-size: 8px">Pagos Compartidos: {{number_format($item->shared_payment, 2)}}</p>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <br>
    @endisset
    <table class="table" style="width: 100%;">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th class="text-center">Código</th>
                <th class="text-center">Descripción</th>
                <th class="text-center">Cantidad</th>
                <th class="text-center">UM</th>
                <th class="text-center">Val. Unit</th>
                <th class="text-center">IVA</th>
                <th class="text-center">IC</th>
                <th class="text-center">Dcto</th>
                <th class="text-center">Val. Item</th>
            </tr>
        </thead>
        <tbody>
            <?php $ItemNro = 0; $TotalDescuentosEnLineas = 0; ?>
            @foreach($request['credit_note_lines'] as $item)
                <?php $ItemNro = $ItemNro + 1; ?>
                <tr>
                    @inject('um', 'App\Models\UnitMeasure')
                    <td>{{$ItemNro}}</td>
                    <td>{{$item['code']}}</td>
                    <td>
                        @if(isset($item['notes']))
                            {{$item['description']}}
                            <p style="font-style: italic; font-size: 7px"><strong>Nota: {{$item['notes']}}</strong></p>
                        @else
                            {{$item['description']}}
                        @endif
                    </td>
                    <td class="text-right">{{number_format($item['invoiced_quantity'], 2)}}</td>
                    <td class="text-right">{{$um->findOrFail($item['unit_measure_id'])['name']}}</td>

                    @if(isset($item['tax_totals']))
                        @if(isset($item['allowance_charges']))
                            <td class="text-right">{{number_format(($item['line_extension_amount'] + $item['allowance_charges'][0]['amount']) / $item['invoiced_quantity'], 2)}}</td>
                        @else
                            <td class="text-right">{{number_format($item['line_extension_amount'] / $item['invoiced_quantity'], 2)}}</td>
                        @endif
                    @else
                        @if(isset($item['allowance_charges']))
                            <td class="text-right">{{number_format(($item['line_extension_amount'] + $item['allowance_charges'][0]['amount']) / $item['invoiced_quantity'], 2)}}</td>
                        @else
                            <td class="text-right">{{number_format($item['line_extension_amount'] / $item['invoiced_quantity'], 2)}}</td>
                        @endif
                    @endif

                    @if(isset($item['tax_totals']))
                        @if(isset($item['tax_totals'][0]['tax_amount']))
                            @if($item['tax_totals'][0]['tax_id'] == 1)
                                <td class="text-right">{{number_format($item['tax_totals'][0]['tax_amount'] / $item['invoiced_quantity'], 2)}}</td>
                            @else
                                <td></td>
                            @endif
                        @else
                            <td class="text-right">{{number_format(0, 2)}}</td>
                        @endif
                    @else
                        <td class="text-right">E</td>
                    @endif

                    @if(isset($item['tax_totals']))
                        @if(isset($item['tax_totals'][1]['tax_amount']))
                            <td class="text-right">{{number_format($item['tax_totals'][1]['tax_amount'] / $item['invoiced_quantity'], 2)}}</td>
                        @else
                            <td class="text-right">{{number_format(0, 2)}}</td>
                        @endif
                    @else
                        <td class="text-right">{{number_format(0, 2)}}</td>
                    @endif

                    @if(isset($item['allowance_charges']))
                        <?php $TotalDescuentosEnLineas = $TotalDescuentosEnLineas + $item['allowance_charges'][0]['amount'] ?>
                        <td class="text-right">{{number_format($item['allowance_charges'][0]['amount'] / $item['invoiced_quantity'], 2)}}</td>
                        @if(isset($item['tax_totals']))
                            <td class="text-right">{{number_format(($item['line_extension_amount'] + $item['tax_totals'][0]['tax_amount']), 2)}}</td>
                        @else
                            <td class="text-right">{{number_format(($item['line_extension_amount']), 2)}}</td>
                        @endif
                    @else
                        <td class="text-right">{{number_format("0", 2)}}</td>
                        <td class="text-right">{{number_format($item['invoiced_quantity'] * ($item['line_extension_amount'] / $item['invoiced_quantity']), 2)}}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
    <br>

    <table class="table" style="width: 100%;">
        <thead>
            <tr>
                <th class="text-center">Impuestos</th>
                <th class="text-center">Retenciones</th>
                <th class="text-center">Totales</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 33.33%;">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">Base</th>
                                <th class="text-center">Porcentaje</th>
                                <th class="text-center">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(isset($request->tax_totals))
                                <?php $TotalImpuestos = 0; ?>
                                @foreach($request->tax_totals as $item)
                                    <tr>
                                        <?php $TotalImpuestos = $TotalImpuestos + $item['tax_amount'] ?>
                                        @inject('tax', 'App\Models\Tax')
                                        <td>{{$tax->findOrFail($item['tax_id'])['name']}}</td>
                                        <td class="text-right">{{number_format($item['taxable_amount'], 2)}}</td>
                                        <td class="text-right">{{number_format($item['percent'], 2)}}%</td>
                                        <td class="text-right">{{number_format($item['tax_amount'], 2)}}</td>
                                    </tr>
                                @endforeach
                            @else
                                <?php $TotalImpuestos = 0; ?>
                            @endif
                        </tbody>
                    </table>
                </td>
                <td style="width: 33.33%;">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">Base</th>
                                <th class="text-center">Porcentaje</th>
                                <th class="text-center">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $TotalRetenciones = 0; ?>
                            @if(isset($withHoldingTaxTotal))
                                @foreach($withHoldingTaxTotal as $item)
                                    <tr>
                                        <?php $TotalRetenciones = $TotalRetenciones + $item['tax_amount'] ?>
                                        @inject('tax', 'App\Models\Tax')
                                        <td>{{$tax->findOrFail($item['tax_id'])['name']}}</td>
                                        <td class="text-right">{{number_format($item['taxable_amount'], 2)}}</td>
                                        <td class="text-right">{{number_format($item['percent'], 2)}}%</td>
                                        <td class="text-right">{{number_format($item['tax_amount'], 2)}}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </td>
                <td style="width: 33.33%;">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="text-center">Concepto</th>
                                <th class="text-center">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Nro Lineas:</td>
                                <td class="text-right">{{$ItemNro}}</td>
                            </tr>
                            <tr>
                                <td>Base:</td>
                                <td class="text-right">{{number_format($request->legal_monetary_totals['line_extension_amount'], 2)}}</td>
                            </tr>
                            <tr>
                                <td>Impuestos:</td>
                                <td class="text-right">{{number_format($TotalImpuestos, 2)}}</td>
                            </tr>
                            <tr>
                                <td>Retenciones:</td>
                                <td class="text-right">{{number_format($TotalRetenciones, 2)}}</td>
                            </tr>
                            <tr>
                                <td>Descuentos En Lineas:</td>
                                <td class="text-right">{{number_format($TotalDescuentosEnLineas, 2)}}</td>
                            </tr>
                            <tr>
                                <td>Descuentos Globales:</td>
                                @if(isset($request->legal_monetary_totals['allowance_total_amount']))
                                    <td class="text-right">{{number_format($request->legal_monetary_totals['allowance_total_amount'], 2)}}</td>
                                @else
                                    <td class="text-right">{{number_format(0, 2)}}</td>
                                @endif
                            </tr>
                            <tr>
                                <td>Total Nota:</td>
                                @if(isset($request->tarifaica))
                                    @if(isset($request->legal_monetary_totals['allowance_total_amount']))
                                        <td class="text-right">{{number_format(round($request->legal_monetary_totals['payable_amount'] + 0, 2), 2)}}</td>
{{--                                        <td class="text-right">{{number_format(round($request->legal_monetary_totals['payable_amount'] + $request->legal_monetary_totals['allowance_total_amount'], 2), 2)}}</td>   --}}
                                    @else
                                        <td class="text-right">{{number_format(round($request->legal_monetary_totals['payable_amount'] + 0, 2), 2)}}</td>
                                    @endif
                                @else
                                    <td class="text-right">{{number_format(round($request->legal_monetary_totals['payable_amount'] + 0, 2), 2)}}</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
    <br>

    <table style="border-collapse: collapse; width: 100%;">
        <tbody>
            <tr>
                <td>
                    @inject('Varios', 'App\Custom\NumberSpellOut')
                    <p style="font-size: 12px; font-weight: bold;">NOTAS:</p>
                    <p style="font-style: italic; font-size: 9px">{{$notes}}</p>
                    <br>
                    @if(isset($request->tarifaica))
                        @if(isset($request->legal_monetary_totals['allowance_total_amount']))
{{--                            <p style="font-size: 12px; font-weight: bold;">SON: <span style="font-weight: normal;">{{$Varios->convertir(round($request->legal_monetary_totals['payable_amount'] + $request->legal_monetary_totals['allowance_total_amount'], 2), $request->idcurrency)}} M/CTE*********.</span></p> --}}
                            <p style="font-size: 12px; font-weight: bold;">SON: <span style="font-weight: normal;">{{$Varios->convertir(round($request->legal_monetary_totals['payable_amount'] + 0, 2), $request->idcurrency)}} M/CTE*********.</span></p>
                        @else
                            <p style="font-size: 12px; font-weight: bold;">SON: <span style="font-weight: normal;">{{$Varios->convertir(round($request->legal_monetary_totals['payable_amount'] + 0, 2), $request->idcurrency)}} M/CTE*********.</span></p>
                        @endif
                    @else
                        <p style="font-size: 12px; font-weight: bold;">SON: <span style="font-weight: normal;">{{$Varios->convertir(round($request->legal_monetary_totals['payable_amount'] + 0, 2), $request->idcurrency)}} M/CTE*********.</span></p>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

        <div class="summary" >
            <div id="note">
                @if(isset($request->disable_confirmation_text))
                    @if(!$request->disable_confirmation_text)
                        <p>CUALQUIER INQUIETUD SOBRE ESTE DOCUMENTO AL TELEFONO {{$company->phone}} o al e-mail {{$user->email}}<br>
                            <br>
                            <p><strong style="font-weight: bold;">FIRMA ACEPTACIÓN:</strong></p><br>
                            <p><strong style="font-weight: bold;">CC:</strong></p><br>
                            <p><strong style="font-weight: bold;">FECHA:</strong></p><br>
                        </p>
                    @endif
                @endif
            </div>
        </div>
        <br/>
    </div>
</body>
</html>
