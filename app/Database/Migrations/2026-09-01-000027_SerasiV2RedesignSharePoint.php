<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

/**
 * SERASI v2 — Drop tabel lama berbasis Graph API, buat ulang dengan skema SharePoint.
 * Tabel: serasi_folders, serasi_documents, serasi_metadata, serasi_tags, serasi_document_tags
 */
class SerasiV2RedesignSharePoint extends Migration
{
    private array $tables = [
        'serasi_document_tags',
        'serasi_metadata',
        'serasi_documents',
        'serasi_folders',
        'serasi_tags',
        // tabel lama Graph-API-era
        'arsip_serasi_tags',
        'arsip_serasi',
    ];

    public function up(): void
    {
        // ── Drop tabel lama (reverse FK order) ─────────────────────
        foreach ($this->tables as $t) {
            $this->forge->dropTable($t, true);
        }

        // ─────────────────────────────────────────────────────────────
        // serasi_tags
        // ─────────────────────────────────────────────────────────────
        $this->forge->addField([
            'tag_id'     => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'tag_name'   => ['type'=>'VARCHAR','constraint'=>100],
            'warna'      => ['type'=>'VARCHAR','constraint'=>20,'null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('tag_id', true);
        $this->forge->addUniqueKey('tag_name');
        $this->forge->createTable('serasi_tags');

        // ─────────────────────────────────────────────────────────────
        // serasi_folders  — struktur folder SharePoint
        // ─────────────────────────────────────────────────────────────
        $this->forge->addField([
            'folder_id'              => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'sharepoint_folder_id'   => ['type'=>'VARCHAR','constraint'=>512,'null'=>true,'comment'=>'UniqueId dari SharePoint REST API'],
            'folder_name'            => ['type'=>'VARCHAR','constraint'=>300],
            'full_path'              => ['type'=>'VARCHAR','constraint'=>1000,'comment'=>'Server relative URL'],
            'parent_folder_id'       => ['type'=>'INT','unsigned'=>true,'null'=>true],
            'item_count'             => ['type'=>'INT','default'=>0],
            'synced_at'              => ['type'=>'DATETIME','null'=>true],
            'created_at'             => ['type'=>'DATETIME','null'=>true],
            'updated_at'             => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('folder_id', true);
        $this->forge->addKey('parent_folder_id');
        $this->forge->addKey('sharepoint_folder_id');
        $this->forge->createTable('serasi_folders');

        // ─────────────────────────────────────────────────────────────
        // serasi_documents  — metadata dokumen dari SharePoint
        // ─────────────────────────────────────────────────────────────
        $this->forge->addField([
            'document_id'          => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'sharepoint_file_id'   => ['type'=>'VARCHAR','constraint'=>512,'null'=>true,'comment'=>'UniqueId dari SharePoint REST API'],
            'file_name'            => ['type'=>'VARCHAR','constraint'=>500],
            'file_extension'       => ['type'=>'VARCHAR','constraint'=>20,'null'=>true],
            'file_size'            => ['type'=>'BIGINT','unsigned'=>true,'default'=>0,'comment'=>'Bytes'],
            'folder_id'            => ['type'=>'INT','unsigned'=>true,'null'=>true],
            'file_url'             => ['type'=>'VARCHAR','constraint'=>1000,'null'=>true,'comment'=>'ServerRelativeUrl'],
            'sharepoint_web_url'   => ['type'=>'VARCHAR','constraint'=>1000,'null'=>true,'comment'=>'URL buka di browser'],
            'created_date'         => ['type'=>'DATETIME','null'=>true],
            'modified_date'        => ['type'=>'DATETIME','null'=>true],
            'status'               => ['type'=>'ENUM','constraint'=>['aktif','arsip','dihapus'],'default'=>'aktif'],
            'created_by'           => ['type'=>'INT','unsigned'=>true,'null'=>true],
            'updated_by'           => ['type'=>'INT','unsigned'=>true,'null'=>true],
            'created_at'           => ['type'=>'DATETIME','null'=>true],
            'updated_at'           => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('document_id', true);
        $this->forge->addKey('folder_id');
        $this->forge->addKey('sharepoint_file_id');
        $this->forge->addKey('status');
        $this->forge->createTable('serasi_documents');

        // ─────────────────────────────────────────────────────────────
        // serasi_metadata  — metadata kurator per dokumen
        // ─────────────────────────────────────────────────────────────
        $this->forge->addField([
            'metadata_id'   => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'document_id'   => ['type'=>'INT','unsigned'=>true],
            'jenis_dokumen' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'Policy Brief','Policy Paper','Policy Memo','Telaah',
                    'Kajian Analisis','Draf Kajian','Rancangan Akhir Kajian',
                    'Notula','Daftar Hadir','POK','KAK','Laporan','SK Tim','Lainnya',
                ],
                'default'    => 'Lainnya',
            ],
            'tahun'         => ['type'=>'YEAR','null'=>true],
            'program'       => ['type'=>'VARCHAR','constraint'=>200,'null'=>true],
            'sub_kegiatan'  => ['type'=>'VARCHAR','constraint'=>200,'null'=>true],
            'tim_penyusun'  => ['type'=>'VARCHAR','constraint'=>200,'null'=>true],
            'kata_kunci'    => ['type'=>'TEXT','null'=>true],
            'created_at'    => ['type'=>'DATETIME','null'=>true],
            'updated_at'    => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('metadata_id', true);
        $this->forge->addUniqueKey('document_id');     // 1:1 dengan dokumen (UNIQUE juga berfungsi sebagai index)
        $this->forge->addForeignKey('document_id','serasi_documents','document_id','CASCADE','CASCADE');
        $this->forge->createTable('serasi_metadata');

        // ─────────────────────────────────────────────────────────────
        // serasi_document_tags  — pivot M:M
        // ─────────────────────────────────────────────────────────────
        $this->forge->addField([
            'id'          => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'document_id' => ['type'=>'INT','unsigned'=>true],
            'tag_id'      => ['type'=>'INT','unsigned'=>true],
            'created_at'  => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['document_id','tag_id']);
        $this->forge->addForeignKey('document_id','serasi_documents','document_id','CASCADE','CASCADE');
        $this->forge->addForeignKey('tag_id','serasi_tags','tag_id','CASCADE','CASCADE');
        $this->forge->createTable('serasi_document_tags');
    }

    public function down(): void
    {
        foreach (['serasi_document_tags','serasi_metadata','serasi_documents','serasi_folders','serasi_tags'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
