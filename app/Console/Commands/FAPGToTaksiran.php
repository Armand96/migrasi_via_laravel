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
    protected $signature = 'update:fapg_taksir {--limit=-1}';

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
        // $sql = "SELECT
        //     idFAPG,
        //     noKtp
        // FROM tran_fapg
        // ";

        $stringss = '';
        $limit = $this->option('limit') == -1 ? -1 : $this->option('limit');//-1;
        if ($limit != -1) {
            $stringss = "LIMIT $limit,20000";
        }

        $sql = "SELECT
            idFAPG,
            no_fatg
        FROM tran_fapg fpg
        LEFT JOIN tran_taksirawal awl ON awl.idTaksirAwal = fpg.idTaksirAwal
        LEFT JOIN metta_taksirawal mtawl ON no_fatg = fkFatg
        WHERE idFAPG = 215561
        $stringss
        ";

        $dataFAPG = DB::connection('mysql')->select(DB::raw($sql));
        $tempData = [];

        foreach ($dataFAPG as $index => $dataF) {
            $sqlKedua = "SELECT
                karat,
                berat_bersih,
                berat_kotor,
                keterangan_barang,
                jumlah,
                nilai_taksir
            FROM metta_taksiran_dan_detail
            WHERE fk_fatg = '$dataF->no_fatg'";
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
                    $dtInsT['totalTaksiran'] += $dataT->nilai_taksir;
                }

                $dtInsT['namaJaminan'] = substr($dtInsT['namaJaminan'], 0, -2);
                $dtInsT['karat'] = substr($dtInsT['karat'], 0, -2);
                $dtInsT['beratKotor'] = substr($dtInsT['beratKotor'], 0, -2);
                $dtInsT['beratBersih'] = substr($dtInsT['beratBersih'], 0, -2);
                $dtInsT['avgKarat'] = $tempAvgKarat / count($dataTaksir);

                $dtInsTApprove = $dtInsT;
                $dtInsTApprove['levelPenaksir'] = 1;
                $dtInsTApprove['isFinal'] = 1;

                // array_push($tempData, $dtInsT);
                // array_push($tempData, $dtInsTApprove);

                $dtInsT = (object) $dtInsT;
                $dtInsTApprove = (object) $dtInsTApprove;

                $sqlInsertStatement = "INSERT INTO tran_taksiran (
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
