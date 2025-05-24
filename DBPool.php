<?php
/**
 * 数据库连接池管理类
 * 实现数据库连接的复用和管理
 */
class DBPool {
    private static $instance;
    private $pool;
    private $config;
    
    /**
     * 私有构造方法
     * @param array $dbconfig 数据库配置
     */
    private function __construct($dbconfig) {
        $this->config = $dbconfig;
        $this->pool = new SplQueue();
        
        // 初始化连接池
        for($i = 0; $i < 5; $i++) {
            $this->pool->enqueue($this->createConnection());
        }
    }
    
    /**
     * 获取连接池单例
     * @param array $dbconfig 数据库配置
     * @return DBPool 连接池实例
     */
    public static function getInstance($dbconfig) {
        if (self::$instance === null) {
            self::$instance = new self($dbconfig);
        }
        return self::$instance;
    }
    
    /**
     * 创建新的数据库连接
     * @return PDO 数据库连接
     */
    private function createConnection() {
        try {
            $conn = new PDO(
                "mysql:host=".$this->config['host'].";dbname=".$this->config['dbname'].";port=".$this->config['port'],
                $this->config['user'],
                $this->config['pwd']
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch(PDOException $e) {
            throw new Exception("数据库连接失败: ".$e->getMessage());
        }
    }
    
    /**
     * 从连接池获取连接
     * @return PDO 数据库连接
     */
    public function getConnection() {
        if($this->pool->isEmpty()) {
            return $this->createConnection();
        }
        return $this->pool->dequeue();
    }
    
    /**
     * 释放连接回连接池
     * @param PDO $conn 数据库连接
     */
    public function releaseConnection($conn) {
        $this->pool->enqueue($conn);
    }
}