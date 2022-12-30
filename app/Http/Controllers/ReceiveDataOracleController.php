<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiveDataOracleController extends Controller
{

    public function receiveData(Request $request)
    {
        $dataReq = $request->all();
        $dataInsertToProMas = array();
        $responseMessage = array(
            'Success' => 0,
            'Message' => '',
            'NoVoucher' => ''
        );

        try {
            DB::beginTransaction();

            if(isset($dataReq['Source']))
            {
                if($dataReq['Source'] == "BAT")
                {
                    $dataInsertToProMas = $dataReq;
                    DB::connection('mysql')->table('acc_in_oracle')->insert($dataInsertToProMas);
                }
                elseif($dataReq['Source'] == "AR_RECEIPT" || $dataReq['Source'] == "AP_PAYMENT")
                {
                    $dataInsertToProMas = $dataReq;
                    $dataInsertProMasDetail = $dataReq['Detail'];
                    unset($dataInsertToProMas['Detail']);

                    DB::connection('mysql')->table('acc_in_oracle')->insert($dataInsertToProMas);

                    $lastIdTransaksi = DB::connection('mysql')->table('acc_in_oracle')->select('idTransaksi', 'TrxNumber')->orderBy('idTransaksi', 'desc')->first();

                    foreach ($dataInsertProMasDetail as $index => $data) {
                        $dataInsertProMasDetail[$index]->idTransaksi = $lastIdTransaksi->idTransaksi;
                        $dataInsertProMasDetail[$index]->TrxNumber = $lastIdTransaksi->TrxNumber;
                    }

                    DB::connection('mysql')->table('acc_in_oracle_detail')->insert($dataInsertProMasDetail);
                }
            } else $responseMessage['Message'] = "Source Tidak Ada";

        } catch (\Throwable $th) {
            $responseMessage['Message'] = $th->getMessage();
            Log::warning($th->getMessage());
        }

        return response()->json($responseMessage);
    }

}
