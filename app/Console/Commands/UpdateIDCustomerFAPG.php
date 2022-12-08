<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateIDCustomerFAPG extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:cust_fapg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update IdCustomer FAPG';

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
        $sql = "UPDATE tran_fapg fapg
                LEFT JOIN tblcustomer cst ON cst.noKtp = fapg.noKtp
                SET fapg.idCustomer = cst.idCustomer WHERE fapg.idCustomer IS NULL";
        DB::connection('mysql')->statement($sql);
        return 0;
    }
}
