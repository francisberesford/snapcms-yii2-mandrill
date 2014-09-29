<?php

namespace snapcms\mandrill;

use Yii;
use yii\mail\BaseMailer;

/**
 * @author Francis Beresford <francis@snapfrozen.com>
 */
class Mailer extends BaseMailer
{
    /**
     * @var string message default class name.
     */
    public $messageClass = 'snapcms\mandrill\Message';
    
    /**
     *
     * @var string your Mandrill API key
     */
    public $apiKey;

    /**
     * @var \Swift_Mailer Swift mailer instance.
     */
    private $_mandrill;

    /**
     * @return array|\Mandrill Swift mailer instance or array configuration.
     */
    public function getMandrill()
    {
        if (!is_object($this->_mandrill))
        {
            $this->_mandrill = new \Mandrill($this->apiKey);
        }
        return $this->_mandrill;
    }

    /**
     * @inheritdoc
     */
    protected function sendMessage($message)
    {
        $address = $message->getTo();
        if (is_array($address)) {
            $address = implode(', ', array_keys($address));
        }
        Yii::info('Sending email "' . $message->getSubject() . '" to "' . $address . '"', __METHOD__);
        
        try {
            $this->Mandrill->messages->send($message->getMandrillMessageArray());
        } catch (Mandrill_Error $e) {
            \Yii::error('A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage(), __METHOD__);
            return false;
        }
        return true;
    }
}
