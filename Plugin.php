<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 发送评论消息到企业微信的应用中
 *
 * @package WeComComment
 * @author  靳闯博客
 * @version 1.0.0
 * @link    https://me.jinchuang.org/
 */
class WeComComment_Plugin implements Typecho_Plugin_Interface
{
    /**
     * Activate the plugin and hook it into the comment submission process
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('WeComComment_Plugin', 'sendMessage');
        return _t('Plugin activated successfully.');
    }

    /**
     * Deactivate the plugin
     */
    public static function deactivate()
    {
        return _t('Plugin deactivated successfully.');
    }

    /**
     * Get plugin configuration
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $corpId = new Typecho_Widget_Helper_Form_Element_Text('corpId', null, '', _t('企业ID'), _t('企业微信的企业ID,（企业微信后台【我的企业】 查看）'));
        $form->addInput($corpId);

        $agentId = new Typecho_Widget_Helper_Form_Element_Text('agentId', null, '', _t('应用Agentld'), _t('企业微信应用的Agentld,(企业微信后台【应用管理】 创建应用)'));
        $form->addInput($agentId);

        $secret = new Typecho_Widget_Helper_Form_Element_Text('secret', null, '', _t('应用Secret'), _t('企业微信应用的Secret，(企业微信后台【应用管理】 点进应用查看Secret)'));
        $form->addInput($secret);

        $toUser = new Typecho_Widget_Helper_Form_Element_Text('toUser', null, '', _t('用户账号'), _t('接收应用消息通知的用户,(企业微信后台【通讯录】 查看用户账号)'));
        $form->addInput($toUser);
    }

    /**
     * Save plugin configuration
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {}

    /**
     * Sends the comment content to WeChat Work when a comment is posted.
     */
    public static function sendMessage($comment)
    {
        // Get the plugin options
        $options = Helper::options()->plugin('WeComComment');
        $corpId = $options->corpId;
        $secret = $options->secret;
        $agentId = $options->agentId;
        $toUser = $options->toUser;

        // 获取访问token
        $token = self::getToken($corpId, $secret);
        if (!$token) {
            error_log('Failed to retrieve WeChat Work token');
            return;
        }

        // 获取评论文章标题
        $db = Typecho_Db::get();
        $select = $db->select('title')
                     ->from('table.contents')
                     ->where('cid = ?', $comment->cid);
        $postTitle = $db->fetchRow($select)['title'];

        // 消息内容准备
        $content = "留言用户:【{$comment->author}】\n" 
                 . "文章标题:【{$postTitle}】\n" 
                 . "留言内容: {$comment->text}";
        
        $url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=$token";
        $data = [
            // 部门id
            // "toparty" => $toUser,

            // 用户id
            "touser" => $toUser,
            "msgtype" => "text",
            "agentid" => $agentId,
            "text" => ["content" => $content],
            "safe" => "0"
        ];

        // 发送消息
        $response = self::postRequest($url, json_encode($data));
        if ($response['errmsg'] == 'ok') {
            error_log('WeChat Work message sent successfully');
        } else {
            error_log('Failed to send WeChat Work message: ' . $response['errmsg']);
        }
    }

    /**
     * Get the WeChat Work access token
     */
    private static function getToken($corpId, $secret)
    {
        $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$corpId&corpsecret=$secret";
        $response = self::getRequest($url);
        if ($response && $response['errmsg'] == 'ok') {
            return $response['access_token'];
        }
        return false;
    }

    /**
     * Helper function to make GET requests
     */
    private static function getRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }

    /**
     * Helper function to make POST requests
     */
    private static function postRequest($url, $postData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }
}

