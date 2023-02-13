<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PostToOraclePerWilayah extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'post:oracle_wilayah {--limit=100} {--wilayah=0}';

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
        if($this->option('wilayah') == 0) {
            Log::warning('wilayah tidak boleh kosong');
            echo "wilayah tidak boleh kosong";
            return;
        }

        echo "wilayah ".$this->option('wilayah')."\n";
        $batchName = str_pad($this->option('wilayah'),4,0, STR_PAD_LEFT);
        if($this->option('wilayah') == 9) $batchName = '0'.str_pad($this->option('wilayah'),3,9, STR_PAD_LEFT);

        $dateNows = date('Y-m-d H:i:s');
        $limit = $this->option('limit');
        $filterWilayah = $this->option('wilayah') == 0? '' : "AND (LEFT(CONVERT(coaCabang, UNSIGNED), 1) = ".$this->option('wilayah').")";
        if($this->option('wilayah') == 9) $filterWilayah = "AND (LEFT(CONVERT(coaCabang, UNSIGNED), 1) = ".$this->option('wilayah')." OR LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 1)";

        try {
            /* AMBIL DATA DARI TRANSAKSI SUMMARY */
            $sqlBatch = "SELECT
                DISTINCT aks.batch, aks.idSummary
            FROM `akunting_summary` aks
            INNER JOIN akunting_detail akd ON akd.idSummary = aks.idSummary
            WHERE isPost = 0 $filterWilayah LIMIT $limit";

            $dataBatch = DB::connection('mysql')->select(DB::raw($sqlBatch));
            $stringDataBatch = "";
            echo "query awal \n";

            if(count($dataBatch)>0)
            {
                /* DIJADIKAN SEBAGAI PARAMETER IN */
                foreach ($dataBatch as $key => $value) {
                    $stringDataBatch .= "$value->idSummary,";
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
                            idDetail,
                            kodeTransaksi AS TrxNumber,
                            namaJenisJurnalOracle AS TrxType,
                            DATE_FORMAT(dt.tanggal, '%d-%b-%y') AS TrxDate,
                            sm.batch,
                            CASE
                                WHEN LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 1 OR LEFT(CONVERT(coaCabang, UNSIGNED), 1) = 9 THEN '999'
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
                                WHEN ct.accountType = 'Bank' THEN '000'
                                WHEN SUBSTR(kodeTransaksi, 6, 3) = 'PTC' THEN rpc.kodeCostCenter
                                WHEN SUBSTR(kodeTransaksi, 6, 3) = 'ADV' THEN adv.kodeCostCenter
                                WHEN SUBSTR(kodeTransaksi, 6, 3) = 'RKN' THEN rb.kodeCostCenter
                                ELSE '000'
                            END AS CostCenter,
                            ct.coaOracle AS NaturalAccount,
                            lineId AS LineID,
                            CASE
                                WHEN ct.accountType = 'Bank' THEN '0000'
                                WHEN tbp.productOracleValue IS NULL THEN '0000'
                                ELSE productOracleValue
                            END AS Product,
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
                        FROM `akunting_detail` dt
                        LEFT JOIN akunting_summary sm ON sm.idSummary = dt.idSummary
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
                            LEFT JOIN tblcostcenter tcc ON tcc.idCostCenter = rb.idCostCenter
                        ) AS rb ON rb.noVoucher = kodeTransaksi
                        LEFT JOIN (
                            SELECT kodeVoucher, CASE WHEN kodeCostCenter IS NULL THEN '000' ELSE kodeCostCenter END AS kodeCostCenter
                            FROM realisasi_pettycash rpc
                            LEFT JOIN tblcostcenter tcc ON tcc.idCostCenter = rpc.idCostCenter
                        ) AS rpc ON rpc.kodeVoucher = kodeTransaksi
                        WHERE sm.idSummary IN ($stringDataBatch)
                    ) AS tblall";

                $dataBatchDetail = DB::connection('mysql')->select(DB::raw($sqlDetail));

                echo "LINE ID \n";

                $batchTemp = [];
                $batchDetailTemp = [];

                foreach ($dataBatch as $bIndex => $bVal) {
                    $bVal->isPost = 1;
                    $bVal->tanggalPost = $dateNows;
                    $rowIndex = 1;
                    array_push($batchTemp, (array) $bVal);
                    foreach ($dataBatchDetail as $key => $value) {
                        if($value->batch == $bVal->batch) {
                            if($dataBatchDetail[$key]->LineID == null) $dataBatchDetail[$key]->LineID = intval($value->batch . $rowIndex);
                            $rowIndex++;
                        }
                        if($bIndex == 0) unset($dataBatchDetail[$key]->coaCabang);
                    }
                }

                foreach ($dataBatchDetail as $key => $value) {
                    $dataUpdateDetail = array(
                        'idDetail' => $value->idDetail,
                        'lineId' => $dataBatchDetail[$key]->LineID,
                        'statusOracle' => 0,
                        'idBatch' => 0,
                    );
                    array_push($batchDetailTemp, $dataUpdateDetail);
                }

                DB::beginTransaction();

                DB::connection('mysql')->table('akunting_summary_oracle_temp')->insert($batchTemp);
                DB::connection('mysql')->table('akunting_detail_oracle_temp')->insert($batchDetailTemp);

                DB::commit();

                $headers = array(
                    'Authorization' => env('AUTH_KEY_ORACLE'),
                    'Content-Type' => 'text/plain'
                );

                $dataPost = array(
                    'BatchName' => date('m/d/Y-H:i:s', strtotime($dateNows)).'-'.$batchName,
                    'CountLine' => count($dataBatchDetail),
                    'Journal' => $dataBatchDetail
                );

                $fileNamePath = "public/data_oracle_".date("Y_m_d_H_i_s").'-'.$batchName.".json";
                Storage::put($fileNamePath, json_encode($dataPost));

                echo "Kirim Data \n";
                $response = Http::withHeaders($headers)->post(env('URL_ORACLE'), $dataPost);
                $bodyResponse = json_decode($response->body());

                if(!isset($bodyResponse->status)) {
                    Log::alert($bodyResponse);
                    Log::alert('pengiriman tidak sukses');
                    $this->deleteDataTemp();
                    return 1;
                    dd('error');
                }

                $batchOracleInsert = array(
                    'tanggalJam' => date('Y-m-d H:i:s' ,strtotime($dateNows)),
                    'isStatus' => 0,
                    'sent' => $fileNamePath,
                    'response' => $response,
                );

                /* SET RESPONSE */
                if($bodyResponse->status == 'success') $batchOracleInsert['isStatus'] = 1;
                else $batchOracleInsert['isStatus'] = 2;

                echo "MASUKIN DATA KE DB BATCH\n";
                /* INSERT TO DB BATCH ORACLE */
                $dataBatch = DB::connection('mysql')->table('acc_batch_oracle')->insertGetId($batchOracleInsert);
                $lastInsertBatchID = DB::connection('mysql')->table('acc_batch_oracle')->select('idBatch')->orderBy('idBatch', 'desc')->first();

                echo "MASUKIN DATA KE DB DETAIL\n";
                /* UPDATE AKUNTING_DETAIL */
                $sqlUpdateBatch = "UPDATE akunting_summary a
                INNER JOIN akunting_summary_oracle_temp b ON a.idSummary = b.idSummary
                SET a.isPost = b.isPost, a.tanggalPost = b.tanggalPost";

                $sqlUpdateDetail = "UPDATE akunting_detail a
                INNER JOIN akunting_detail_oracle_temp b ON a.idDetail = b.idDetail
                SET a.idBatch = $lastInsertBatchID->idBatch, a.statusOracle = 1, a.lineId = b.lineId;";

                $sqlDeleteTempTableBatch = "DELETE FROM akunting_summary_oracle_temp";
                $sqlDeleteTempTableDetail = "DELETE FROM akunting_detail_oracle_temp;";

                DB::beginTransaction();

                DB::connection('mysql')->update($sqlUpdateBatch);
                DB::connection('mysql')->update($sqlUpdateDetail);
                DB::connection('mysql')->delete($sqlDeleteTempTableBatch);
                DB::connection('mysql')->delete($sqlDeleteTempTableDetail);

                DB::commit();

                // dd($dataBatch, $lastInsertBatchID);
            }
            else
            {
                echo "tidak ada record \n";
            }
        } catch (\Throwable $th) {
            $this->deleteDataTemp();
            throw $th;
        }

        return 0;
    }

    function deleteDataTemp()
    {
        $sqlDeleteTempTableBatch = "DELETE FROM akunting_summary_oracle_temp";
        $sqlDeleteTempTableDetail = "DELETE FROM akunting_detail_oracle_temp;";
        DB::connection('mysql')->delete($sqlDeleteTempTableBatch);
        DB::connection('mysql')->delete($sqlDeleteTempTableDetail);
    }
}
