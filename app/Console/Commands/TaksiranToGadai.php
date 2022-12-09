<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TaksiranToGadai extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:taksir_gadai';

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
            idTaksiran,
            fpg.noKtp,
            fpg.idCabang,
            fpg.idCustomer,
            fpg.idFAPG,
            referensiNpk,
            referensiNama,
            referensiCif
        FROM tran_taksiran_copy1 tks
        LEFT JOIN tran_fapg fpg ON tks.idFAPG = fpg.idFAPG
        LEFT JOIN tblcustomer cst ON cst.idCustomer = fpg.idCustomer
        WHERE isFinal = 1";

        $dataTaksiran = DB::connection('mysql')->select(DB::raw($sql));

        $dataTemp = [];

        if(count($dataTaksiran))
        {
            foreach ($dataTaksiran as $indexT => $dataT) {
                $sql = "SELECT
                    $dataT->idCabang AS idCabang,
                    $dataT->idCustomer AS idCustomer,
                    $dataT->idFAPG AS idFAPG,
                    $dataT->idTaksiran AS idTaksiran,
                    idProduk,
                    1 AS transKe,
                    tgl_pengajuan AS tglPengajuan,
                    no_sbg AS noSbg,
                    no_sbg AS noSbgCopy1,
                    no_sbg AS noSbgCopy2,
                    no_sbg AS noSbgCopy3,
                    total_nilai_pinjaman AS pengajuanPinjaman,
                    pembulatan,
                    total_nilai_pinjaman AS nilaiPinjaman,
                    biaya_admin_awal AS biayaAdminAwal,
                    diskon_admin AS diskonAdmin,
                    biaya_admin AS biayaAdmin,
                    nilai_ap_customer AS nilaiApCustomer,
                    lama_pinjaman AS lamaPinjaman,
                    tgl_jatuh_tempo AS tglJatuhTempo,
                    min_rate AS minRate,
                    rate_flat AS rateFlat,
                    biaya_penyimpanan AS biayaPenyimpanan,
                    akumulasi_os_pokok AS akumulasiOsPokok,
                    total_obligor AS totalObligor,
                    idAsalJaminan,
                    ttt.idTujuanTransaksi,
                    idJenisReferensi,
                    '$dataT->referensiNpk' AS referensiNpk,
                    '$dataT->referensiNama' AS referensiNama,
                    '$dataT->referensiCif' AS referensiCif,
                    pembayaran AS jenisPembayaran,
                    idBank AS idBankPencairan,
                    nm_ref_tr AS statusAplikasi,
                    idProgram,
                    idSektorEkonomi,
                    approval_ltv AS approvalLtv,
                    approval_uang_pinjaman AS approvalUangPinjaman,
                    approval_rate_flat AS approvalRateFlat,
                    approval_one_obligor AS approvalOneObligor,
                    final_approval AS approvalFinal,
                    keterangan,
                    isstatus AS isStatus,
                    -- status_data AS isStatus,
                    CASE WHEN status_approval = 'Approve' THEN 1 WHEN status_approval = 'Batal' THEN 2 ELSE 0 END AS isStatusGadai,
                    0 idApprovalKapos,
                    0 idApprovalKaunit,
                    0 idApprovalKacab,
                    0 idApprovalKaarea,
                    0 idApprovalKawil,
                    0 idApprovalDirektur,
                    0 idApprovalDirut,
                    alasan_approve_kapos AS ketApproveKapos,
                    alasan_approve_kaunit AS ketApproveKaunit,
                    alasan_approve_kacab AS ketApproveKacab,
                    alasan_approve_kaarea AS ketApproveKaarea,
                    alasan_approve_kawil AS ketApproveKawil,
                    alasan_approve_direktur AS ketApproveDirektur,
                    alasan_approve_dirut AS ketApproveDirut,
                    is_approval_kapos AS isApprovalKapos,
                    is_approval_kaunit AS isApprovalKaunit,
                    is_approval_kacab AS isApprovalKacab,
                    is_approval_kaarea AS isApprovalKaarea,
                    is_approval_kawil AS isApprovalKawil,
                    is_approval_direktur AS isApprovalDirektur,
                    is_approval_dirut AS isApprovalDirut,
                    tgl_approval_kapos AS tglApprovalKapos,
                    tgl_approval_kaunit AS tglApprovalKaunit,
                    tgl_approval_kacab AS tglApprovalKacab,
                    tgl_approval_kaarea AS tglApprovalKaarea,
                    tgl_approval_kawil AS tglApprovalKawil,
                    tgl_approval_direktur AS tglApprovalDirektur,
                    tgl_approval_dirut AS tglApprovalDirut,
                    0 AS idApprovalFinal,
                    1 AS isApprovalFinal,
                    tgl_cair AS tglCair,
                    tgl_cair AS tglPendanaan,
                    status_funding AS statusFunding,
                    0 AS idUserCetakSbg ,
                    is_cetak_sbg AS isCetakSbg,
                    0 AS idUserCetakKwitansi,
                    0 AS idUserInput
                FROM dummy_gadai
                LEFT JOIN tblprogram ON fk_program = kodeProgram
                LEFT JOIN tblbank ON fk_bank_pencairan_ap = kd_bank
                LEFT JOIN tbljenisreferensi ON asal_aplikasi = tbljenisreferensi.namaJenisReferensi
                LEFT JOIN tbltujuantransaksi ttt ON fk_tujuan_transaksi = kodeTujuanTransaksi
                LEFT JOIN tblasaljaminan ON asal_jaminan = tblasaljaminan.namaAsalJaminan
                LEFT JOIN tblproduk ON fk_produk = kodeProduk
                LEFT JOIN tblsektorekonomi ON fk_sektor_ekonomi = kodeSektorEkonomi
                WHERE no_id = '$dataT->noKtp' LIMIT 1
                ";

                $dataGadai = DB::connection('mysql')->select(DB::raw($sql));

                if(count($dataGadai))
                {
                    foreach ($dataGadai as $index => $dataG) {
                        array_push($dataTemp, (array) $dataG);
                    }
                }
                // dd($sql);
                echo "$indexT \n";
            }

            DB::connection('mysql')->table('trans_gadai_copy1')->insert($dataTemp);
            // dd($dataTemp);
        }

        return 0;
    }
}
