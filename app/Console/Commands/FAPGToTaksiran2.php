<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FAPGToTaksiran2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:fapg_taksir2';

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
            no_fatg,

            karat,
            berat_bersih,
            berat_kotor,
            keterangan_barang,
            jumlah,
            nilai_taksir
        FROM tran_fapg fpg
        LEFT JOIN tran_taksirawal awl ON awl.idTaksirAwal = fpg.idTaksirAwal
        LEFT JOIN metta_taksirawal mtawl ON no_fatg = fkFatg
        LEFT JOIN metta_taksiran_dan_detail ON fk_fatg = no_fatg
        ";

        $dataTaksir = DB::connection('mysql')->select(DB::raw($sql));

        $tempAvgKarat = 0;
        $countSameData = 0;

        $dtInsTemp = array(
            'idFAPG' => 0,
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

        $tempDataInsert = [];

        if(count($dataTaksir))
        {
            echo "masuk foreach \n";
            foreach ($dataTaksir as $indexTaksir => $dataT) {
                echo "$dataT->idFAPG --- $indexTaksir \n";

                if($indexTaksir == 0)
                {
                    $countSameData++;
                    $dtInsTemp['idFAPG'] = $dataT->idFAPG;
                    $dtInsTemp['jumlahBarang'] += $dataT->jumlah;
                    $dtInsTemp['namaJaminan'] .= str_replace("'","", $dataT->keterangan_barang.", ");
                    $dtInsTemp['karat'] .= $dataT->karat.", ";
                    $dtInsTemp['beratKotor'] .= $dataT->berat_kotor.", ";
                    $dtInsTemp['beratBersih'] .= $dataT->berat_bersih.", ";
                    $tempAvgKarat += $dataT->karat;
                    $dtInsTemp['sumBeratKotor'] += $dataT->berat_kotor;
                    $dtInsTemp['sumBeratBersih'] += $dataT->berat_bersih;
                    $dtInsTemp['totalTaksiran'] += $dataT->nilai_taksir;
                }
                else
                {

                    if($dataTaksir[$indexTaksir-1]->no_fatg == $dataT->no_fatg)
                    {
                        $countSameData++;
                        $dtInsTemp['idFAPG'] = $dataT->idFAPG;
                        $dtInsTemp['jumlahBarang'] += $dataT->jumlah;
                        $dtInsTemp['namaJaminan'] .= str_replace("'","", $dataT->keterangan_barang.", ");
                        $dtInsTemp['karat'] .= $dataT->karat.", ";
                        $dtInsTemp['beratKotor'] .= $dataT->berat_kotor.", ";
                        $dtInsTemp['beratBersih'] .= $dataT->berat_bersih.", ";
                        $tempAvgKarat += $dataT->karat;
                        $dtInsTemp['sumBeratKotor'] += $dataT->berat_kotor;
                        $dtInsTemp['sumBeratBersih'] += $dataT->berat_bersih;
                        $dtInsTemp['totalTaksiran'] += $dataT->nilai_taksir;
                    }
                    else
                    {
                        // if($countSameData == 0 && $indexTaksir != 0) dd($dataT);
                        $dtInsTemp['namaJaminan'] = substr($dtInsTemp['namaJaminan'], 0, -2);
                        $dtInsTemp['karat'] = substr($dtInsTemp['karat'], 0, -2);
                        $dtInsTemp['beratKotor'] = substr($dtInsTemp['beratKotor'], 0, -2);
                        $dtInsTemp['beratBersih'] = substr($dtInsTemp['beratBersih'], 0, -2);
                        $dtInsTemp['avgKarat'] = $tempAvgKarat / $countSameData;

                        array_push($tempDataInsert, $dtInsTemp);

                        $countSameData = 1;
                        $dtInsTemp = array(
                            'idFAPG' => 0,
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

                        $dtInsTemp['idFAPG'] = $dataT->idFAPG;
                        $dtInsTemp['jumlahBarang'] += $dataT->jumlah;
                        $dtInsTemp['namaJaminan'] .= str_replace("'","", $dataT->keterangan_barang.", ");
                        $dtInsTemp['karat'] .= $dataT->karat.", ";
                        $dtInsTemp['beratKotor'] .= $dataT->berat_kotor.", ";
                        $dtInsTemp['beratBersih'] .= $dataT->berat_bersih.", ";
                        $tempAvgKarat += $dataT->karat;
                        $dtInsTemp['sumBeratKotor'] += $dataT->berat_kotor;
                        $dtInsTemp['sumBeratBersih'] += $dataT->berat_bersih;
                        $dtInsTemp['totalTaksiran'] += $dataT->nilai_taksir;

                    }
                    if(count($dataTaksir)-1 == $indexTaksir && $dataTaksir[$indexTaksir-1]->no_fatg == $dataT->no_fatg ) array_push($tempDataInsert, $dtInsTemp);
                }


            }

            Storage::disk('public')->put('data.json', json_encode($tempDataInsert));

            // $dtInsTApprove = $dtInsT;
            // $dtInsTApprove['levelPenaksir'] = 1;
            // $dtInsTApprove['isFinal'] = 1;

            // $dtInsT = (object) $dtInsT;
            // $dtInsTApprove = (object) $dtInsTApprove;

            // DB::connection('mysql')->statement($sqlInsertStatement);
        }
        return 0;
    }
}
