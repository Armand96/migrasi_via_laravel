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

        /* CHECK AUTH */
        $key = $request->header('Authorization');

        if($key == env('APP_KEY_API'))
        {
            try {
                DB::beginTransaction();

                if(isset($dataReq['Source']))
                {
                    if($dataReq['Source'] == "BAT")
                    {

                        $dataVoucher = DB::connection('mysql')->select(DB::raw("CALL oracle_transaksi_internal('".$dataReq['Outlet']."', '".$dataReq['BankIn']."', '".$dataReq['BankOut']."')"));
                        // dd($dataVoucher);
                        /* VALIDASI BANK DAN OUTLET */
                        if(
                            ($dataVoucher[0]->idBankIn == NULL || $dataVoucher[0]->idBankIn == 0) ||
                            ($dataVoucher[0]->idBankOut == NULL || $dataVoucher[0]->idBankOut == 0) ||
                            ($dataVoucher[0]->idCabang == NULL || $dataVoucher[0]->idCabang == 0)
                        )
                        {
                            DB::rollBack();
                            $stringMessage = "";
                            if($dataVoucher[0]->idBankIn == NULL || $dataVoucher[0]->idBankIn == 0) $stringMessage .= "Bank Masuk tidak terdaftar di promas ";
                            if($dataVoucher[0]->idBankOut == NULL || $dataVoucher[0]->idBankOut == 0) $stringMessage .= "Bank Keluar tidak terdaftar di promas ";
                            if($dataVoucher[0]->idCabang == NULL || $dataVoucher[0]->idCabang == 0) $stringMessage .= "Cabang atau Outlet tidak terdaftar di promas ";
                            $stringMessage = substr($stringMessage, 0, -1);

                            $responseMessage['Message'] = $stringMessage;

                            return response()->json($responseMessage);
                        }

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

                        $idBATOracle = 0; //idJenisTransaksi Untuk Oracle

                        $dtInsertInternal = array(
                            'idCabang' => $dataVoucher[0]->idCabang,
                            'idCabangKeluar' => 0,
                            'idJenisTransaksi' => $idBATOracle,
                            'idBankKeluar' => $dataVoucher[0]->idBankIn,
                            'idBankMasuk' => $dataVoucher[0]->idBankOut,
                            'tanggal' => $dataVoucher[0]->tanggal,
                            'tahun' => $dataVoucher[0]->tahun,
                            'kodeTrans' => $dataVoucher[0]->kodeTrans,
                            'urutVoucher' => $dataVoucher[0]->urutVoucher,
                            'noVoucher' => $dataVoucher[0]->noVoucher,
                            'nominalTransaksi' => $dataReq['TotalAmount'],
                            'keteranganTransaksi' => $dataReq['Description']
                        );

                        DB::connection('mysql')->table('fa_transaksiinternal')->insert($dtInsertInternal);

                        $responseMessage['Success'] = 1;
                        $responseMessage['Message'] = "Success";
                        $responseMessage['NoVoucher'] = $dataVoucher[0]->noVoucher;

                    }
                    elseif($dataReq['Source'] == "AR_RECEIPT" || $dataReq['Source'] == "AP_PAYMENT")
                    {
                        $dataVoucher = DB::connection('mysql')->select(DB::raw("CALL oracle_rekonbank('".$dataReq['Outlet']."', '".$dataReq['BankID']."')"));

                        /* VALIDASI BANK DAN CABANG */
                        if(
                            ($dataVoucher[0]->idBank == NULL || $dataVoucher[0]->idBank == 0) ||
                            ($dataVoucher[0]->idCabang == NULL || $dataVoucher[0]->idCabang == 0)
                        )
                        {
                            DB::rollBack();
                            $stringMessage = "";
                            if($dataVoucher[0]->idBank == NULL || $dataVoucher[0]->idBank == 0) $stringMessage .= "Bank tidak terdaftar di promas ";
                            if($dataVoucher[0]->idCabang == NULL || $dataVoucher[0]->idCabang == 0) $stringMessage .= "Cabang atau Outlet tidak terdaftar di promas ";
                            $stringMessage = substr($stringMessage, 0, -1);

                            $responseMessage['Message'] = $stringMessage;

                            return response()->json($responseMessage);
                        }

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

                DB::rollBack();
                $responseMessage['Message'] = $th->getMessage();
                Log::error($th->getMessage());
                // throw $th;
            }
        }
        else
        {
            $responseMessage['Message'] = "Invalid Auth Key";
        }



        return response()->json($responseMessage);
    }

}
