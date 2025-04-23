<?php


namespace zohomail\craftzohomail\migrations;

use Craft;
use craft\db\Migration;

class Install extends Migration
{
    
    /**
     * @var string The database driver to use
     */
    public $driver;
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->createTables();
        Craft::$app->db->schema->refresh();
       

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        
        $this->dropTables();

        return true;
    }


    
    public function createTables(): void
    {
        if (!$this->db->tableExists('{{%zoho_mail_config}}')) 
        {
            $this->createTable('{{%zoho_mail_config}}', [
                'id' => $this->primaryKey(),
                'domain' => $this->string()->notNull(),
                'redirect_url' => $this->text()->notNull(),
                'client_id' => $this->string()->notNull(),
                'client_secret' => $this->text()->notNull(),
                'access_token' => $this->text()->notNull(),
                'refresh_token' => $this->text()->notNull(),
                'created_at' => $this->bigInteger()->notNull(),
                'updated_at' =>$this->bigInteger()->notNull()
            ]);

        }
        
    }

    
    public function dropTables(): void
    {
        $this->dropTableIfExists('{{%zoho_mail_config}}');
        Craft::$app->getCache()->delete('zoho_mail_config_properties');
    }

    

}
