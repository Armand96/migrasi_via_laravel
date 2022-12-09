<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FAPGToTaksiran extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:fapg_taksir';

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
        $sql = "SELECT
            idFAPG,
            noKtp
        FROM tran_fapg_copy1
        -- WHERE idFAPG > 1112 AND idFAPG < 10000
        -- WHERE idFAPG >= 10000 AND idFAPG < 20000
        -- WHERE idFAPG >= 20000 AND idFAPG < 30000
        -- WHERE idFAPG >= 30000 AND idFAPG < 40000
        -- WHERE idFAPG >= 40000 AND idFAPG < 50000
        -- WHERE idFAPG >= 50000 AND idFAPG < 60000
        -- WHERE idFAPG >= 60000 AND idFAPG < 70000
        -- WHERE idFAPG >= 70000 AND idFAPG < 80000
        -- WHERE idFAPG >= 80000 AND idFAPG < 90000
        -- WHERE idFAPG >= 90000 AND idFAPG < 100000
        -- WHERE idFAPG >= 100000 AND idFAPG < 110000
        -- WHERE idFAPG >= 110000 AND idFAPG < 120000
        ";
        $dataFAPG = DB::connection('mysql')->select(DB::raw($sql));

        foreach ($dataFAPG as $index => $dataF) {
            $sqlKedua = "SELECT
                karat,
                berat_bersih,
                berat_kotor,
                keterangan_barang,
                jumlah,
                total_takasir
            FROM dummy_getDataTaksir
            WHERE no_id = '$dataF->noKtp'";
            $dataTaksir = DB::connection('mysql')->select(DB::raw($sqlKedua));

            $dtInsT = array(
                'idFAPG' => $dataF->idFAPG,
                'jumlahBarang' => 0,
                'namaJaminan' => "",
                'karat' => "",
                'beratKotor' => "",
                'beratBersih' => "",
                'avgKarat' => 0,
                'sumBeratKotor' => 0,
                'sumBeratBersih' => 0,
                'totalTaksiran' => 0,
                'keterangan' => "",
                'levelPenaksir' => 0,
                'isFinal' => 0,
            );

            $tempAvgKarat = 0;

            if(count($dataTaksir))
            {
                foreach ($dataTaksir as $indexTaksir => $dataT) {
                    $dtInsT['jumlahBarang'] += $dataT->jumlah;
                    $dtInsT['namaJaminan'] .= str_replace("'","", $dataT->keterangan_barang.", ");
                    $dtInsT['karat'] .= $dataT->karat.", ";
                    $dtInsT['beratKotor'] .= $dataT->berat_kotor.", ";
                    $dtInsT['beratBersih'] .= $dataT->berat_bersih.", ";
                    $tempAvgKarat += $dataT->karat;
                    $dtInsT['sumBeratKotor'] += $dataT->berat_kotor;
                    $dtInsT['sumBeratBersih'] += $dataT->berat_bersih;
                    $dtInsT['totalTaksiran'] += $dataT->total_takasir;
                }

                $dtInsT['namaJaminan'] = substr($dtInsT['namaJaminan'], 0, -2);
                $dtInsT['karat'] = substr($dtInsT['karat'], 0, -2);
                $dtInsT['beratKotor'] = substr($dtInsT['beratKotor'], 0, -2);
                $dtInsT['beratBersih'] = substr($dtInsT['beratBersih'], 0, -2);
                $dtInsT['avgKarat'] = $tempAvgKarat / count($dataTaksir);

                $dtInsTApprove = $dtInsT;
                $dtInsTApprove['levelPenaksir'] = 1;
                $dtInsTApprove['isFinal'] = 1;

                $dtInsT = (object) $dtInsT;
                $dtInsTApprove = (object) $dtInsTApprove;

                $sqlInsertStatement = "INSERT INTO tran_taksiran_copy1 (
                    idFAPG,
                    jumlahBarang,
                    namaJaminan,
                    karat,
                    beratKotor,
                    beratBersih,
                    avgKarat,
                    sumBeratKotor,
                    sumBeratBersih,
                    totalTaksiran,
                    keterangan,
                    levelPenaksir,
                    isFinal
                ) VALUES(
                    $dtInsT->idFAPG,
                    $dtInsT->jumlahBarang,
                    '$dtInsT->namaJaminan',
                    '$dtInsT->karat',
                    '$dtInsT->beratKotor',
                    '$dtInsT->beratBersih',
                    $dtInsT->avgKarat,
                    $dtInsT->sumBeratKotor,
                    $dtInsT->sumBeratBersih,
                    $dtInsT->totalTaksiran,
                    '$dtInsT->keterangan',
                    $dtInsT->levelPenaksir,
                    $dtInsT->isFinal
                ), (
                    $dtInsTApprove->idFAPG,
                    $dtInsTApprove->jumlahBarang,
                    '$dtInsTApprove->namaJaminan',
                    '$dtInsTApprove->karat',
                    '$dtInsTApprove->beratKotor',
                    '$dtInsTApprove->beratBersih',
                    $dtInsTApprove->avgKarat,
                    $dtInsTApprove->sumBeratKotor,
                    $dtInsTApprove->sumBeratBersih,
                    $dtInsTApprove->totalTaksiran,
                    '$dtInsTApprove->keterangan',
                    $dtInsTApprove->levelPenaksir,
                    $dtInsTApprove->isFinal
                )";

                // dd($sqlInsertStatement);

                DB::connection('mysql')->statement($sqlInsertStatement);
            }

            echo "$index $dataF->idFAPG \n";
            // dd($dataTaksir, $dtInsT, $dtInsTApprove);
        }
        return 0;
    }
}
