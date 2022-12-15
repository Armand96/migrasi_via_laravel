<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateGadaiJF extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:gadai_jf';

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
            idGadai,
            noSbgCopy1
        FROM trans_gadai_copy2 cpy";

        $dataJF = DB::connection('mysql')->select(DB::raw($sql));

        $tempData = [];

        if(count($dataJF))
        {
            foreach ($dataJF as $key => $value) {
                $sqlSub = "SELECT
                    $value->idGadai AS idGadai,
                    isjf AS isJF,
                    CASE WHEN idjf IS NULL THEN 0 ELSE idjf END AS idJf
                FROM dummy_gadai
                WHERE no_sbg = '$value->noSbgCopy1' LIMIT 1";
                $subData = DB::connection('mysql')->select(DB::raw($sqlSub));

                if(count($subData))
                {
                    foreach ($subData as $subKey => $subValue) {
                        $sqlUpdate = "UPDATE trans_gadai_copy2 SET isJf = $subValue->isJF, idJf = $subValue->idJf WHERE idGadai = $subValue->idGadai";
                        DB::connection('mysql')->statement($sqlUpdate);
                    }
                    // DB::connection('mysql')->table('trans_gadai_copy2')->where('idJf')->update((array) $subData[0]);
                    // array_push($tempData, (array) $subData[0]);
                }
                echo "$key \n";
            }
        }
        // dd($tempData);
        return 0;
    }
}
