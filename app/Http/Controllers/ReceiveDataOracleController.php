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
        // dd($dataReq);

        try {
            DB::beginTransaction();

            if(isset($dataReq['Source']))
            {
                if($dataReq['Source'] == "BAT")
                {
                    $dataInsertToProMas = array(
                        'TrxNumber' => $dataReq['TrxNumber'],
                        'Source' => $dataReq['Source'],
                        'TrxStatus' => $dataReq['TrxStatus'],
                        'TrxDate' => date('Y-m-d', strtotime($dataReq['TrxDate'])),
                        'BankIn' => $dataReq['BankIn'],
                        'BankOut' => $dataReq['BankOut'],
                        'BankID' => '0',
                        'TotalAmount' => $dataReq['TotalAmount'],
                        'Outlet' => $dataReq['Outlet'],
                        'Description' => $dataReq['Description'],
                        'LineID' => $dataReq['LineID'],
                        'NaturalAccountIn' => $dataReq['NaturalAccountIn'],
                        'NaturalAccountOut' => $dataReq['NaturalAccountOut'],
                        'NaturalAccount' => '0',

                    );
                    DB::connection('mysql')->table('acc_in_oracle')->insert($dataInsertToProMas);

                    $responseMessage['Success'] = 1;
                    $responseMessage['Message'] = "Success";
                    $responseMessage['NoVoucher'] = "";

                }
                elseif($dataReq['Source'] == "AR_RECEIPT" || $dataReq['Source'] == "AP_PAYMENT")
                {
                    $dataVoucher = DB::connection('mysql')->select(DB::raw("CALL oracle_rekonbank('".$dataReq['Outlet']."', '".$dataReq['BankID']."')"));
                    // dd($dataVoucher);

                    $dataInsertToProMas = array(
                        'TrxNumber' => $dataReq['TrxNumber'],
                        'Source' => $dataReq['Source'],
                        'TrxStatus' => $dataReq['TrxStatus'],
                        'TrxDate' => date('Y-m-d', strtotime($dataReq['TrxDate'])),
                        'BankIn' => 0,
                        'BankOut' => 0,
                        'BankID' => $dataReq['BankID'],
                        'TotalAmount' => $dataReq['TotalAmount'],
                        'Outlet' => $dataReq['Outlet'],
                        'Description' => $dataReq['Description'],
                        'LineID' => 0,
                        'NaturalAccountIn' => 0,
                        'NaturalAccountOut' => 0,
                        'NaturalAccount' => $dataReq['NaturalAccount']
                    );
                    $dataInsertProMasDetail = $dataReq['Detail'];
                    unset($dataInsertToProMas['Detail']);

                    DB::connection('mysql')->table('acc_in_oracle')->insert($dataInsertToProMas);

                    $lastIdTransaksi = DB::connection('mysql')->table('acc_in_oracle')->select('idTransaksi', 'TrxNumber')->orderBy('idTransaksi', 'desc')->first();
                    // dd($lastIdTransaksi);

                    foreach ($dataInsertProMasDetail as $index => $data) {
                        $dataInsertProMasDetail[$index]['idTransaksi'] = $lastIdTransaksi->idTransaksi;
                        $dataInsertProMasDetail[$index]['TrxNumber'] = $lastIdTransaksi->TrxNumber;

                        $dataRekonBank = array(
                            'idCabang'=>$dataVoucher[0]->idBank,
                            'idBank'=>$dataVoucher[0]->idCabang,
                            'tanggal'=>$dataVoucher[0]->tanggal,
                            'tahun'=>$dataVoucher[0]->tahun,
                            'kodeTrans'=>$dataVoucher[0]->kodeTrans,
                            'urutVoucher'=>$dataVoucher[0]->urutVoucher,
                            'noVoucher'=>$dataVoucher[0]->noVoucher,
                            'idCreated'=>0,
                            'dateCreated'=>date('Y-m-d'),
                            'total'=>$data['Amount'],
                            'idApproval'=>0,
                            'isActive'=>1,
                            'statusApproval'=>0,
                            'fromOracle'=>1,
                            'tipeUangOracle'=>$data['TipeUang']
                        );


                        DB::connection('mysql')->table('fa_rekonbank')->insert($dataRekonBank);
                        $resultRekonBank = DB::connection('mysql')->table('fa_rekonbank')->select('idRekonBank')->orderBy('idRekonBank')->first();

                        $dataRekonBankDetail = array(
                            'idRekonBank'=>$resultRekonBank->idRekonBank,
                            'idCoa'=>0,
                            'nominal'=>$dataRekonBank['total'],
                            'catatan'=>$data['LineDescription'],
                            'typeTransaksi'=>$data['TipeUang'] == "Keluar" ? "K" : "D",
                            'idCashflow'=>0,
                            'idCostCenter'=>0,
                            'bankReference'=>'',
                            'coaOracle'=>$dataInsertToProMas['NaturalAccount']
                        );
                        DB::connection('mysql')->table('fa_rekonbank_detail')->insert($dataRekonBankDetail);
                    }

                    DB::connection('mysql')->table('acc_in_oracle_detail')->insert($dataInsertProMasDetail);

                    $responseMessage['Success'] = 1;
                    $responseMessage['Message'] = "Success";
                    $responseMessage['NoVoucher'] = $dataVoucher[0]->noVoucher;
                }
            } else $responseMessage['Message'] = "Source Tidak Ada";

            DB::commit();

        } catch (\Throwable $th) {
            // $responseMessage['Message'] = $th->getMessage();
            // Log::warning($th->getMessage());
            throw $th;
        }

        return response()->json($responseMessage);
    }

}
