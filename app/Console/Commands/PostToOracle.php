<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PostToOracle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'post:oracle {--limit=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // dd(date('m/d/Y-H:i:s'));
        $limit = $this->option('limit');
        // dd($limit);

        /* AMBIL DATA DARI TRANSAKSI SUMMARY */
        $sqlBatch = "SELECT batch, idSmTrans FROM acc_sm_trans WHERE isPost = 0 LIMIT $limit";
        $dataBatch = DB::connection('mysql')->select(DB::raw($sqlBatch));
        $stringDataBatch = "";

        if(count($dataBatch)>0)
        {
            /* DIJADIKAN SEBAGAI PARAMETER IN */
            foreach ($dataBatch as $key => $value) {
                $stringDataBatch .= "$value->batch,";
            }
            $stringDataBatch = substr($stringDataBatch, 0, -1);

            $sqlDetail = "SELECT
                *,
                CASE
                    WHEN coaCabang = '1971000999' THEN CONCAT('0', COMPANY)
                    WHEN coaCabang LIKE '1971000%' AND coaCabang <> '1971000999' THEN RIGHT(coaCabang, 4)
                    ELSE '0000'
                END AS Intercompany
                FROM
                (
                    SELECT
                        idDtTrans,
                        kodeTransaksi AS TrxNumber,
                        namaJenisJurnal AS TrxType,
                        DATE_FORMAT(dt.tanggal, '%d-%b-%y') AS TrxDate,
                        sm.batch,
                        CASE
                            WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 1 THEN '999'
                            WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 2 THEN '002'
                            WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 3 THEN '003'
                            WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 4 THEN '004'
                            WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 5 THEN '005'
                            WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 6 THEN '006'
                            WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 7 THEN '007'
                            WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 8 THEN '008'
                        END AS Company,
                        CONCAT('0', LEFT(coaCabang, 3)) AS Outlet,
                        1 AS TipePembiayaan,
                        CASE
                            WHEN SUBSTR(kodeTransaksi, 6, 3) = 'PTC' THEN rpc.kodeCostCenter
                            WHEN SUBSTR(kodeTransaksi, 6, 3) = 'ADV' THEN adv.kodeCostCenter
                            WHEN SUBSTR(kodeTransaksi, 6, 3) = 'RKN' THEN rb.kodeCostCenter
                            ELSE '000'
                        END AS CostCenter,
                        ct.coaOracle AS NaturalAccount,
                        CASE WHEN tbp.productOracleValue IS NULL THEN '0000' ELSE productOracleValue END AS Product,
                        RIGHT(coaCabang, 10) AS coaCabang,
                        '000' AS Future1,
                        '000' AS Future2,
                        CASE
                            WHEN dk = 'D' THEN dt.amount ELSE 0
                        END AS Debit,
                        CASE
                            WHEN dk = 'K' THEN dt.amount ELSE 0
                        END AS Credit,
                        dt.keterangan AS Reference,
                        'IDR' AS CurrencyCode,
                        ct.accountType AS AccountType,
                        1 AS ExchangeRate,
                        dt.keterangan AS BankReference
                    FROM `acc_dt_trans` dt
                    LEFT JOIN acc_sm_trans sm ON sm.batch = dt.batch
                    LEFT JOIN tblproduk tbp ON tbp.idProduk = sm.idProduk
                    LEFT JOIN acc_jenisjurnal jj ON jj.idJenisJurnal = sm.idJenisJurnal
                    LEFT JOIN tblcoatemplate ct ON ct.idCoa = dt.idCoa
                    LEFT JOIN (
                        SELECT kodeVoucher, CASE WHEN kodeCostCenter IS NULL THEN '000' ELSE kodeCostCenter END AS kodeCostCenter
                        FROM advance adv
                        LEFT JOIN advance_realisasi_detail adt ON adt.idAdvance = adv.idAdvance
                        LEFT JOIN tblcostcenter tcc ON tcc.idCostCenter = adt.idCostCenter
                    ) AS adv ON adv.kodeVoucher = kodeTransaksi
                    LEFT JOIN (
                        SELECT noVoucher, CASE WHEN kodeCostCenter IS NULL THEN '000' ELSE kodeCostCenter END AS kodeCostCenter
                        FROM fa_rekonbank rb
                        LEFT JOIN fa_rekonbank_detail rbd ON rb.idRekonBank = rbd.idRekonBank
                        LEFT JOIN tblcostcenter tcc ON tcc.idCostCenter = rbd.idCostCenter
                    ) AS rb ON rb.noVoucher = kodeTransaksi
                    LEFT JOIN (
                        SELECT kodeVoucher, CASE WHEN kodeCostCenter IS NULL THEN '000' ELSE kodeCostCenter END AS kodeCostCenter
                        FROM realisasi_pettycash rpc
                        LEFT JOIN realisasi_pettycash_detail rpd ON rpc.idRealisasiPettyCash = rpd.idRealisasiPettyCash
                        LEFT JOIN tblcostcenter tcc ON tcc.idCostCenter = rpd.idCostCenter
                    ) AS rpc ON rpc.kodeVoucher = kodeTransaksi
                    WHERE dt.batch IN ($stringDataBatch)
                ) AS tblall";

            $dataBatchDetail = DB::connection('mysql')->select(DB::raw($sqlDetail));
            // Storage::disk('public')->put('batchdata.json', json_encode($dataBatchDetail));
            // dd($dataBatchDetail);

            DB::beginTransaction();

            foreach ($dataBatch as $bIndex => $bVal) {
                $updateData = array(
                    'isPost' => 1
                );
                DB::connection('mysql')->table('acc_sm_trans')->where('idSmTrans', $bVal->idSmTrans)->update($updateData);
                $rowIndex = 1;
                foreach ($dataBatchDetail as $key => $value) {
                    if($value->batch == $bVal->batch) {
                        $dataBatchDetail[$key]->LineID = intval($value->batch . $rowIndex);
                        $rowIndex++;
                    }
                    if($bIndex == 0) unset($dataBatchDetail[$key]->coaCabang);
                }
            }

            $headers = array(
                'Authorization' => env('AUTH_KEY_ORACLE'),
                'Content-Type' => 'text/plain'
            );

            $dataPost = array(
                'BatchName' => date('m/d/Y-H:i:s'),
                'CountLine' => count($dataBatchDetail),
                'Journal' => $dataBatchDetail
            );

            Storage::put('public/sentdata.json', json_encode($dataPost));

            $response = Http::withHeaders($headers)->post(env('URL_ORACLE'), $dataPost);
            $bodyResponse = json_decode($response->body());

            $batchOracleInsert = array(
                'tanggalJam' => date('Y-m-d H:i:s'),
                'isStatus' => 0,
                'sent' => json_encode($dataPost),
                'response' => $response->body(),
            );

            /* SET RESPONSE */
            if($bodyResponse->status == 'success') $batchOracleInsert['isStatus'] = 1;
            else $batchOracleInsert['isStatus'] = 2;

            /* INSERT TO DB BATCH ORACLE */
            $dataBatch = DB::connection('mysql')->table('acc_batch_oracle')->insertGetId($batchOracleInsert);
            $lastInsertBatchID = DB::connection('mysql')->table('acc_batch_oracle')->select('idBatch')->orderBy('idBatch', 'desc')->first();

            // dd($lastInsertBatchID);

            /* UPDATE ACC_DT_TRANS */
            foreach ($dataBatchDetail as $key => $value) {
                $dataUpdate = array(
                    'lineId' => $value->LineID,
                    'statusOracle' => $batchOracleInsert['isStatus'],
                    'idBatch' => $lastInsertBatchID->idBatch
                );
                DB::connection('mysql')->table('acc_dt_trans')->where('idDtTrans', $value->idDtTrans)->update($dataUpdate);
                // unset($dataBatchDetail[$key]->batch);
            }

            DB::commit();

            // dd($dataBatch, $lastInsertBatchID);

        }


        return 0;
    }
}
