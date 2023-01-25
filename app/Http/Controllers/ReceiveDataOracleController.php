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
            'Status' => 0,
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
                    /* ==================================================== BAT ==================================================== */
                    if($dataReq['Source'] == "BAT")
                    {

                        /* CHECK JIKA TRX SUDAH ADA */
                        $isExist = DB::connection('mysql')->table('acc_in_oracle')
                                    ->where('TrxNumber', $dataReq['TrxNumber'])
                                    ->where('TrxStatus', $dataReq['TrxStatus'])
                                    ->where('LineID', $dataReq['LineID'])->first();

                        if($isExist != null)
                        {
                            DB::rollback();
                            $responseMessage['Message'] = "Nomor Transaksi ".$dataReq['TrxNumber']." dan status ".$dataReq["TrxStatus"]." sudah ada";
                            $this->fail($responseMessage['Message'], $dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                            return response()->json($responseMessage);
                        }

                        /* CHECK STATUS CREATED SUDAH ADA ATAU BELUM JIKA TRX STATUS CANCEL*/
                        if($dataReq['TrxStatus'] == "CANCELED")
                        {
                            $isExist = DB::connection('mysql')->table('acc_in_oracle')
                            ->where('TrxNumber', $dataReq['TrxNumber'])
                            ->where('LineID', $dataReq['LineID'])
                            ->where('TrxStatus', 'CREATED')->first();

                            if($isExist == null)
                            {
                                DB::rollback();
                                $responseMessage['Message'] = "Nomor Transaksi ".$dataReq['TrxNumber']." tidak bisa CANCELED, status CREATED belum ada, ";
                                $this->fail($responseMessage['Message'], $dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                                return response()->json($responseMessage);
                            }
                        }

                        $dataVoucher = DB::connection('mysql')->select(DB::raw("CALL oracle_transaksi_internal('".$dataReq['Outlet']."', '".$dataReq['BankIn']."', '".$dataReq['BankOut']."')"));
                        if(count($dataVoucher) == 0)
                        {
                            /* TIDAK ADA KODE BANK MASUK X DI CABANG X */
                            $dataBankDanCabangKeluar = DB::connection('mysql')->table('fa_saldobank')
                                            ->leftJoin('tblbank', 'tblbank.idBank', '=', 'fa_saldobank.idBank')
                                            ->leftJoin('tblcabang', 'tblcabang.idCabang', '=', 'fa_saldobank.idCabang')
                                            ->where('kd_bank', $dataReq['BankOut'])->where('kodeCabang', $dataReq['Outlet'])
                                            ->select('tblbank.idBank', 'tblcabang.idCabang')->first();
                            if($dataBankDanCabangKeluar == null)
                            {
                                DB::rollback();
                                $responseMessage['Message'] = "Kode Bank Keluar ".$dataReq['BankOut']." di Cabang ".$dataReq['Outlet']." Tidak ada";
                                $this->fail($responseMessage['Message'], $dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                                return response()->json($responseMessage);
                            }

                            /* TIDAK ADA KODE BANK MASUK X DI CABANG X */
                            $dataBankDanCabangMasuk = DB::connection('mysql')->table('fa_saldobank')
                                            ->leftJoin('tblbank', 'tblbank.idBank', '=', 'fa_saldobank.idBank')
                                            ->leftJoin('tblcabang', 'tblcabang.idCabang', '=', 'fa_saldobank.idCabang')
                                            ->where('kd_bank', $dataReq['BankIn'])->where('kodeCabang', $dataReq['Outlet'])
                                            ->select('tblbank.idBank', 'tblcabang.idCabang')->first();
                            if($dataBankDanCabangMasuk == null)
                            {
                                DB::rollback();
                                $responseMessage['Message'] = "Kode Bank Masuk ".$dataReq['BankIn']." di Cabang ".$dataReq['Outlet']." Tidak ada";
                                $this->fail($responseMessage['Message'], $dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                                return response()->json($responseMessage);
                            }

                            $dtArray = array(
                                'idCabang' => $dataBankDanCabangMasuk->idCabang,
                                'idBankIn' => $dataBankDanCabangMasuk->idBank,
                                'idBankOut' => $dataBankDanCabangKeluar->idBank,
                                'tanggal' => date('Y-m-d'),
                                'tahun' => date('Y'),
                                'kodeTrans' => 'MB',
                                'urutVoucher' => 1,
                                'noVoucher' => date('y').date('m').'.MB.'.'000001',
                            );

                            $dtArray = (object) $dtArray;
                            array_push($dataVoucher, $dtArray);
                        }

                        /* VALIDASI BANK DAN OUTLET */
                        if(
                            ($dataVoucher[0]->idBankIn == NULL || $dataVoucher[0]->idBankIn == 0) ||
                            ($dataVoucher[0]->idBankOut == NULL || $dataVoucher[0]->idBankOut == 0) ||
                            ($dataVoucher[0]->idCabang == NULL || $dataVoucher[0]->idCabang == 0)
                        )
                        {
                            DB::rollBack();
                            $stringMessage = "";
                            if($dataVoucher[0]->idBankIn == NULL || $dataVoucher[0]->idBankIn == 0) $stringMessage .= "Kode Bank Masuk ".$dataReq['BankIn']." di Cabang ".$dataReq['Outlet']." Tidak ada ";
                            if($dataVoucher[0]->idBankOut == NULL || $dataVoucher[0]->idBankOut == 0) $stringMessage .= "Kode Bank Keluar ".$dataReq['BankOut']." di Cabang ".$dataReq['Outlet']." Tidak ada ";

                            $stringMessage = substr($stringMessage, 0, -1);

                            DB::rollback();
                            $responseMessage['Message'] = $stringMessage;
                            $this->fail($responseMessage['Message'], $dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                            return response()->json($responseMessage);
                        }

                        /* INSERT KE ACC_IN_ORACLE */
                        $dtInsertOracle = array(
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
                        DB::connection('mysql')->table('acc_in_oracle')->insert($dtInsertOracle);

                        /* INSERT KE TRANSAKASI INTERNAL */
                        $idBATOracle = 31; //idJenisTransaksi Untuk Oracle
                        $dtInsertInternal = array(
                            'idCabang' => $dataVoucher[0]->idCabang,
                            'idCabangKeluar' => 0,
                            'idJenisTransaksi' => $idBATOracle,
                            'idBankKeluar' => $dataVoucher[0]->idBankOut,
                            'idBankMasuk' => $dataVoucher[0]->idBankIn,
                            'tanggal' => $dataVoucher[0]->tanggal,
                            'tahun' => $dataVoucher[0]->tahun,
                            'kodeTrans' => $dataVoucher[0]->kodeTrans,
                            'urutVoucher' => $dataVoucher[0]->urutVoucher,
                            'noVoucher' => $dataVoucher[0]->noVoucher,
                            'saldoBankMasuk' => 0,
                            'saldoBankKeluar' => 0,
                            'nominalTransaksi' => $dataReq['TotalAmount'],
                            'keteranganTransaksi' => $dataReq['Description'],
                            'dateCreated' => date('Y-m-d H:i:s'),
                            'fromOracle' => 1
                        );
                        DB::connection('mysql')->table('fa_transaksiinternal')->insert($dtInsertInternal);

                        /* INSERT KE REPORT_BANKTRANSAKSI */
                        // INSERT DEBET
                        $dtInsertRptBankTrxDebet = array(
                            'idBank' => $dtInsertInternal['idBankMasuk'],
                            'idCabang' => $dtInsertInternal['idCabang'],
                            'idJenisTransaksi' => $idBATOracle,
                            'tanggalSistem' => date('Y-m-d'),
                            'tanggalProses' => date('Y-m-d H:i:s'),
                            'kodeTrans' => $dataVoucher[0]->noVoucher,
                            'keterangan' => $dataReq['Description'],
                            'nominal' => $dtInsertInternal['nominalTransaksi'],
                            'tipeTrx' => 'Debet'
                        );
                        $message = $this->insertReportBankTrx($dtInsertRptBankTrxDebet);

                        /* CHECK SALDO BANK IN*/
                        if($message != null)
                        {
                            DB::rollBack();
                            $responseMessage ['Message']= $message;
                            $this->fail($responseMessage['Message'], $dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                            return response()->json($responseMessage);
                        }

                        // INSERT KREDIT
                        $dtInsertRptBankTrxKredit = $dtInsertRptBankTrxDebet;
                        $dtInsertRptBankTrxKredit['idBank'] = $dtInsertInternal['idBankKeluar'];
                        $dtInsertRptBankTrxKredit['tipeTrx'] = 'Kredit';
                        $this->insertReportBankTrx($dtInsertRptBankTrxKredit);

                        /* CHECK SALDO BANK OUT*/
                        if($message != null)
                        {
                            DB::rollBack();
                            $responseMessage ['Message']= $message;
                            return response()->json($responseMessage);
                        }

                        /* UPDATE SALDO BANK MASUK*/
                        $message = $this->updateSaldoBank(
                            $dtInsertInternal['idBankMasuk'],
                            $dtInsertInternal['idCabang'],
                            'Masuk',
                            $dtInsertInternal['nominalTransaksi'],
                            $dataReq['BankIn'],
                            $dataReq['Outlet']
                        );

                        /* CHECK SALDO BANK */
                        if($message != null)
                        {
                            DB::rollBack();
                            $responseMessage ['Message']= $message;
                            $this->fail($responseMessage['Message'], $dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                            return response()->json($responseMessage);
                        }

                        /* UPDATE SALDO BANK KELUAR*/
                        $message = $this->updateSaldoBank(
                            $dtInsertInternal['idBankKeluar'],
                            $dtInsertInternal['idCabang'],
                            'Keluar',
                            $dtInsertInternal['nominalTransaksi'],
                            $dataReq['BankOut'],
                            $dataReq['Outlet']
                        );

                        /* CHECK SALDO BANK */
                        if($message != null)
                        {
                            DB::rollBack();
                            $responseMessage ['Message']= $message;
                            $this->fail($responseMessage['Message'], $dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                            return response()->json($responseMessage);
                        }

                        /* RETURN RESPONSE */
                        $responseMessage['Status'] = 1;
                        $responseMessage['Message'] = "Success";
                        $responseMessage['NoVoucher'] = $dataVoucher[0]->noVoucher;

                        $this->updateFail($dataReq['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus']);
                    }
                    elseif($dataReq['Source'] == "AR_RECEIPT" || $dataReq['Source'] == "AP_PAYMENT")
                    /* ==================================================== AP_PAYMENT / AR_RECEIPT ==================================================== */
                    {
                        /* CHECK JIKA TRX SUDAH ADA */
                        $isExist = DB::connection('mysql')->table('acc_in_oracle')
                                    ->where('TrxNumber', $dataReq['TrxNumber'])
                                    ->where('TrxStatus', $dataReq['TrxStatus'])->first();

                        if($isExist != null)
                        {
                            $responseMessage['Message'] = "Nomor Transaksi ".$dataReq['TrxNumber']." dan status ".$dataReq["TrxStatus"]." sudah ada";
                            $this->fail($responseMessage['Message'], 0, $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                            return response()->json($responseMessage);
                        }

                        /* CHECK STATUS CREATED SUDAH ADA ATAU BELUM JIKA TRX STATUS CANCEL*/
                        if($dataReq['TrxStatus'] == "CANCELED")
                        {
                            $isExist = DB::connection('mysql')->table('acc_in_oracle')
                            ->where('TrxNumber', $dataReq['TrxNumber'])
                            ->where('TrxStatus', 'CREATED')->first();

                            if($isExist == null)
                            {
                                DB::rollback();
                                $responseMessage['Message'] = "Nomor Transaksi ".$dataReq['TrxNumber']." tidak bisa CANCELED, status CREATED belum ada, ";
                                $this->fail($responseMessage['Message'], 0, $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                                return response()->json($responseMessage);
                            }
                        }

                        /* AMBIL DATA VOUCHER */
                        $dataVoucher = DB::connection('mysql')->select(DB::raw("CALL oracle_rekonbank('".$dataReq['Outlet']."', '".$dataReq['BankID']."')"));
                        if(count($dataVoucher) == 0)
                        {
                            // $dataBankIn = DB::connection('mysql')->table('tblbank')->where('kd_bank', $dataReq['BankID'])->select('idBank')->first();
                            // $dataCabang = DB::connection('mysql')->table('tblcabang')->where('kodeCabang', $dataReq['Outlet'])->select('idCabang')->first();

                            $dataBankDanCabang = DB::connection('mysql')->table('fa_saldobank')
                                            ->leftJoin('tblbank', 'tblbank.idBank', '=', 'fa_saldobank.idBank')
                                            ->leftJoin('tblcabang', 'tblcabang.idCabang', '=', 'fa_saldobank.idCabang')
                                            ->where('kd_bank', $dataReq['BankID'])->where('kodeCabang', $dataReq['Outlet'])
                                            ->select('tblbank.idBank', 'tblcabang.idCabang')->first();

                            if($dataBankDanCabang == null)
                            {
                                DB::rollBack();
                                $responseMessage['Message'] = "Bank ".$dataReq['BankID']." di Cabang ".$dataReq['Outlet']." Tidak ada";
                                $this->fail($responseMessage['Message'], $dataReq['Detail'][0]['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                                return response()->json($responseMessage);
                            }

                            $dtArray = array(
                                'idCabang' => $dataBankDanCabang->idCabang,
                                'idBank' => $dataBankDanCabang->idBank,
                                'tanggal' => date('Y-m-d'),
                                'tahun' => date('Y'),
                                'kodeTrans' => 'RKN',
                                'urutVoucher' => 1,
                                'noVoucher' => date('y').date('m').'.RKN.'.'0000001',
                            );

                            $dtArray = (object) $dtArray;
                            array_push($dataVoucher, $dtArray);
                        }

                        /* VALIDASI BANK DAN CABANG */
                        if(
                            ($dataVoucher[0]->idBank == NULL || $dataVoucher[0]->idBank == 0) ||
                            ($dataVoucher[0]->idCabang == NULL || $dataVoucher[0]->idCabang == 0)
                        )
                        {
                            DB::rollBack();
                            $stringMessage = "";
                            if($dataVoucher[0]->idBank == NULL || $dataVoucher[0]->idBank == 0) $stringMessage .= "Bank ".$dataReq['BankID']." tidak terdaftar di promas ";
                            if($dataVoucher[0]->idCabang == NULL || $dataVoucher[0]->idCabang == 0) $stringMessage .= "Cabang ".$dataReq['Outlet']." tidak terdaftar di promas ";
                            $stringMessage = substr($stringMessage, 0, -1);

                            $responseMessage['Message'] = $stringMessage;
                            $this->fail($responseMessage['Message'], $dataReq['Detail'][0]['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
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

                        /* INSERT TO REKON BANK */
                        $dataRekonBank = array(
                            'idCabang'=>$dataVoucher[0]->idCabang,
                            'idBank'=>$dataVoucher[0]->idBank,
                            'tanggal'=>$dataVoucher[0]->tanggal,
                            'tahun'=>$dataVoucher[0]->tahun,
                            'kodeTrans'=>$dataVoucher[0]->kodeTrans,
                            'urutVoucher'=>$dataVoucher[0]->urutVoucher,
                            'noVoucher'=>$dataVoucher[0]->noVoucher,
                            'idCreated'=>0,
                            'dateCreated'=>date('Y-m-d'),
                            'total'=>$dataReq['TotalAmount'],
                            'idApproval'=>0,
                            'isActive'=>1,
                            'statusApproval'=>0,
                            'fromOracle'=>1,
                            'coaOracle'=>$dataReq['NaturalAccount']
                        );
                        DB::connection('mysql')->table('fa_rekonbank')->insert($dataRekonBank);
                        $resultRekonBank = DB::connection('mysql')->table('fa_rekonbank')->select('idRekonBank')->orderBy('idRekonBank')->first();

                        foreach ($dataInsertProMasDetail as $index => $data) {
                            /* CHECK TIPE UANG */
                            // dd($data);
                            $tipeUangTrx = "";
                            if($data['TipeUang'] == 'Keluar') $tipeUangTrx = "Kredit";
                            else if($data['TipeUang'] == 'Masuk') $tipeUangTrx = "Debet";
                            else {
                                DB::rollBack();
                                $responseMessage['Message'] = "TipeUang Tidak Ada";
                                $this->fail($responseMessage['Message'], $data['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                                return response()->json($responseMessage);
                            }

                            $dataInsertProMasDetail[$index]['idTransaksi'] = $lastIdTransaksi->idTransaksi;
                            $dataInsertProMasDetail[$index]['TrxNumber'] = $lastIdTransaksi->TrxNumber;

                            /* INSERT TO REKON BANK DETAIL */
                            $dataRekonBankDetail = array(
                                'idRekonBank'=>$resultRekonBank->idRekonBank,
                                'idCoa'=>0,
                                'nominal'=>$data['Amount'],
                                'catatan'=>$data['LineDescription'],
                                'typeTransaksi'=>$data['TipeUang'] == "Keluar" ? "K" : "D",
                                'idCashflow'=>0,
                                'idCostCenter'=>0,
                                'bankReference'=>'',
                                'coaOracle'=>$data['NaturalAccount'],
                                'tipeUangOracle'=>$data['TipeUang'],
                            );
                            DB::connection('mysql')->table('fa_rekonbank_detail')->insert($dataRekonBankDetail);

                            /* INSERT TO REPORT TRANSAKSI BANK */
                            $dtInsertRptBankTrx = array(
                                'idBank' => $dataRekonBank['idBank'],
                                'idCabang' => $dataRekonBank['idCabang'],
                                'idJenisTransaksi' => $dataReq['Source'] == 'AR_RECEIPT' ? 33 : 32, // idJenisTransaksi
                                'tanggalSistem' => date('Y-m-d'),
                                'tanggalProses' => date('Y-m-d H:i:s'),
                                'kodeTrans' => $dataVoucher[0]->noVoucher,
                                'keterangan' => $dataReq['Description'],
                                'nominal' => $data['Amount'],
                                'tipeTrx' => $tipeUangTrx
                            );
                            $message = $this->insertReportBankTrx($dtInsertRptBankTrx);
                            /* VALIDASI SALDO BANK */
                            if($message != null)
                            {
                                DB::rollBack();
                                $responseMessage ['Message']= $message;
                                $this->fail($responseMessage['Message'], $data['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                                return response()->json($responseMessage);
                            }

                            /* UPDATE SALDO BANK */
                            $message = $this->updateSaldoBank(
                                $dataRekonBank['idBank'],
                                $dataRekonBank['idCabang'],
                                $data['TipeUang'],
                                $data['Amount'],
                                $dataReq['BankID'],
                                $dataReq['Outlet']
                            );

                            /* UPDATE ERROR JIKA ADA */
                        }

                        DB::connection('mysql')->table('acc_in_oracle_detail')->insert($dataInsertProMasDetail);
                        $this->updateFail($data['LineID'], $dataReq['TrxNumber'], $dataReq['TrxStatus']);

                        $responseMessage['Status'] = 1;
                        $responseMessage['Message'] = "Success";
                        $responseMessage['NoVoucher'] = $dataVoucher[0]->noVoucher;
                    }
                } else $responseMessage['Message'] = "Source Tidak Ada";

                DB::commit();

            } catch (\Throwable $th) {

                DB::rollBack();
                $responseMessage['Message'] = $th->getMessage(). " in line ". $th->getLine();
                Log::error($th->getMessage(). " in line ". $th->getLine());
                $lineId = isset($dataReq['LineID']) ? $dataReq['LineID'] : 0;
                $this->fail($responseMessage['Message'], $lineId, $dataReq['TrxNumber'], $dataReq['TrxStatus'], $dataReq['TrxDate']);
                // throw $th;
            }
        }
        else
        {
            $responseMessage['Message'] = "Invalid Auth Key";
        }

        return response()->json($responseMessage);
    }

    /* ============================================================================ */
    /* UPDATE SALDO BANK */
    public function updateSaldoBank($idBank, $idCabang, $tipeUang, $nominal, $kdBank, $kdCabang)
    {
        $dataSaldoBank = DB::connection('mysql')->table('fa_saldobank')
                        ->where('idBank', $idBank)->where('idCabang', $idCabang)->first();

        if($dataSaldoBank == null)
        {
            $message = "Kode bank $kdBank belum terpasang di cabang $kdCabang";
            return $message;
        }

        if($tipeUang == 'Keluar')
        {
            $dataUpdate = array(
                'saldoKeluar' => $dataSaldoBank->saldoKeluar + $nominal,
                'saldoAkhir' => $dataSaldoBank->saldoAkhir - $nominal
            );
        }
        else
        {
            $dataUpdate = array(
                'saldoMasuk' => $dataSaldoBank->saldoMasuk + $nominal,
                'saldoAkhir' => $dataSaldoBank->saldoAkhir + $nominal
            );
        }

        DB::connection('mysql')->table('fa_saldobank')
        ->where('idSaldoBank', $dataSaldoBank->idSaldoBank)->update($dataUpdate);
    }

    /* INSERT KE REPORT */
    public function insertReportBankTrx($data)
    {
        $dataSaldo = DB::connection('mysql')->table('fa_saldobank')
                            ->where('idBank', $data['idBank'])
                            ->where('idCabang', $data['idCabang'])->first();

        if($dataSaldo == null)
        {
            $kdBank = $data['kdBank'];
            $kdCabang = $data['kdCabang'];
            $message = "Kode bank $kdBank belum terpasang di cabang $kdCabang";
            return $message;
        }

        if($dataSaldo == null) $data['saldoAwal'] = 0;
        else $data['saldoAwal'] = $dataSaldo->saldoAkhir;

        if($data['tipeTrx'] == 'Debet')
        {
            $data['debet'] = $data['nominal'];
            $data['kredit'] = 0;
            $data['saldoAkhir'] = $data['saldoAwal'] + $data['nominal'];
        }
        else
        {
            $data['debet'] = 0;
            $data['kredit'] = $data['nominal'];
            $data['saldoAkhir'] = $data['saldoAwal'] - $data['nominal'];
        }


        unset($data['nominal']);
        unset($data['tipeTrx']);

        DB::connection('mysql')->table('report_banktransaksi')->insert($data);
    }

    public function fail($message, $lineID, $trxNumber, $trxStatus, $trxDate)
    {
        try {
            $data = DB::connection('mysql')->table('acc_in_oracle_fail')
            ->where('lineId', $lineID)->where('trxNumber', $trxNumber)
            ->where('trxStatus', $trxStatus)->first();

            if($data == null)
            {
                $data = array(
                    "trxNumber" => $trxNumber,
                    "trxStatus" => $trxStatus,
                    "lineId" => $lineID,
                    "trxDate" => date('Y-m-d H:i:s', strtotime($trxDate)),
                    "errorMessage" => $message,
                    "statusError" => "ERROR",
                    "createdAt" => date('Y-m-d H:i:s')
                );

                DB::connection('mysql')->table('acc_in_oracle_fail')->insert($data);
            }
            else
            {
                DB::connection('mysql')->table('acc_in_oracle_fail')->where('idFail', $data->idFail)->update(
                    ['errorMessage' => $message]
                );
            }

        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function updateFail($lineID, $trxNumber, $trxStatus)
    {
        $dataFail = DB::connection('mysql')->table('acc_in_oracle_fail')
        ->where('lineId', $lineID)->where('trxNumber', $trxNumber)
        ->where('trxStatus', $trxStatus)->first();

        if($dataFail != null)
        {
            DB::connection('mysql')->table('acc_in_oracle_fail')->where('idFail', $dataFail->idFail)->update(
                ['statusError' => 'SOLVED']
            );
        }
    }

}
