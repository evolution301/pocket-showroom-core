<?php
/**
 * Pocket Showroom - Singleton Trait
 * 
 * 提供单例模式的通用实现，避免在每个类中重复相同的代码
 * 
 * 使用方法:
 *   class My_Class {
 *       use PS_Singleton;
 *       
 *       // 类的其余代码...
 *   }
 *   
 *   // 获取实例
 *   $instance = My_Class::get_instance();
 *
 * @package PocketShowroom
 */

if (!defined('ABSPATH')) {
    exit;
}

trait PS_Singleton
{
    /**
     * 单例实例
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * 获取单例实例
     *
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct()
    {
        // 子类应该在构造函数中添加自己的初始化逻辑
    }

    /**
     * 禁止克隆
     * 
     * @return void
     */
    private function __clone()
    {
        // 不允许克隆
    }

    /**
     * 禁止反序列化
     *
     * @return void
     * @throws \Exception 当尝试反序列化时
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize a singleton.');
    }
}
