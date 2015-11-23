<?php

namespace snapcms\mandrill;

use Yii;
use yii\mail\BaseMessage;
use yii\helpers\HtmlPurifier;
use yii\validators\EmailValidator;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use snapcms\models\Config;

/**
 * @author Francis Beresford <francis@snapfrozen.com>
 * Some parts by Nicola Puddu which were lifted from: https://github.com/nickcv-ln/yii2-mandrill
 */
class Message extends BaseMessage
{
    public $throwErrors = true;
    public $emailErrorMessage = 'Email {type} field "{email}" is not valid';
    private $_to = [];
    private $_cc = [];
    private $_bcc = [];
    private $_from;
    private $_fromName;
    private $_replyTo = [];
    private $_subject;
    private $_htmlBody;
    private $_textBody;
    private $_recipients = [];
    private $_tags = [];
    private $_attachments = [];
    private $_images = [];
    private $_template;
    private $_merge_vars = [];
    private $_global_merge_vars = [];
    private $_finfo = null;

    /**
     * @inheritdoc
     */
    public function setTo($to)
    {
        $this->setRecipients('to', $to);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTo()
    {
        return $this->_to;
    }

    /**
     * @inheritdoc
     */
    public function setCc($cc)
    {
        $this->setRecipients('cc', $cc);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCc()
    {
        return $this->_cc;
    }

    /**
     * @inheritdoc
     */
    public function setBcc($bcc)
    {
        $this->setRecipients('bcc', $bcc);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBcc()
    {
        return $this->_bcc;
    }

    /**
     * @inheritdoc
     */
    public function setFrom($from)
    {
        if (is_string($from) && $this->validateEmail($from))
        {
            $this->_from = $from;
            $this->_fromName = null;
        }

        if (is_array($from))
        {
            $email = key($from);
            $name = array_shift($from);
            if (!$this->validateEmail($email))
            {
                return $this;
            }
            $this->_from = $email;
            $this->_fromName = trim($name);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFrom()
    {
        return $this->_from;
    }

    /**
     * @inheritdoc
     */
    public function setReplyTo($replyTo)
    {
        $this->_replyTo [] = $replyTo;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getReplyTo()
    {
        return $this->_replyTo;
    }

    /**
     * @inheritdoc
     */
    public function setSubject($subject)
    {
        $this->_subject = $subject;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSubject()
    {
        return $this->_subject;
    }

    /**
     * @inheritdoc
     */
    public function setHtmlBody($html)
    {
        $this->_htmlBody = $html;
        return $this;
    }

    public function getHtmlBody()
    {
        return $this->_htmlBody;
    }

    public function setTextBody($text)
    {
        $this->_textBody = HtmlPurifier::process($text);
        return $this;
    }
    
    public function setTemplate($template)
    {
        $this->_template = $template;
        return $this;
    }
    
    public function getTemplate()
    {
        return $this->_template;
    }
    
    public function setMergeVars($vars)
    {
        $this->_merge_vars = $vars;
        return $this;
    }
    
    public function setGlobalMergeVars($vars)
    {
        $this->_global_merge_vars = $vars;
        return $this;
    }

    /**
     * Cannot set charset with Mandrill
     * @param type $charset
     * @return \snapcms\mandrill\Message
     */
    public function setCharset($charset)
    {
        return $this;
    }

    /**
     * Cannot set charset with Mandrill
     */
    public function getCharset()
    {
        return null;
    }

    /**
     * Taken from: https://github.com/nickcv-ln/yii2-mandrill
     * @todo: Test and possibly hook into SnapCMS media files system (also to be created)
     * 
     * Attaches existing file to the email message.
     *
     * @see \snapcms\mandrill\Message::getAttachments() getter
     * @see \snapcms\mandrill\Message::$_attachments private attribute
     *
     * @param string $fileName full file name
     * @param array $options options for embed file. Valid options are:
     *
     * - fileName: name, which should be used to attach file.
     * - contentType: attached file MIME type.
     *
     * @return \snapcms\mandrill\Message
     */
    public function attach($fileName, array $options = array())
    {
        if (file_exists($fileName) && !is_dir($fileName))
        {
            $purifiedOptions = [
                'fileName' => ArrayHelper::getValue($options, 'fileName', basename($fileName)),
                'contentType' => ArrayHelper::getValue($options, 'contentType', FileHelper::getMimeType($fileName)),
            ];
            $this->attachContent(file_get_contents($fileName), $purifiedOptions);
        }
        return $this;
    }

    /**
     * Attach specified content as file for the email message.
     *
     * @see \snapcms\mandrill\Message::getAttachments() getter
     * @see \snapcms\mandrill\Message::$_attachments private attribute
     *
     * @param string $content attachment file content.
     * @param array $options options for embed file. Valid options are:
     *
     * - fileName: name, which should be used to attach file.
     * - contentType: attached file MIME type.
     *
     * @return \snapcms\mandrill\Message
     */
    public function attachContent($content, array $options = [])
    {
        $purifiedOptions = is_array($options) ? $options : [];
        if (is_string($content) && strlen($content) !== 0)
        {
            $this->_attachments[] = [
                'name' => ArrayHelper::getValue($purifiedOptions, 'fileName', ('file_' . count($this->_attachments))),
                'type' => ArrayHelper::getValue($purifiedOptions, 'contentType', $this->getMimeTypeFromBinary($content)),
                'content' => base64_encode($content),
            ];
        }
        return $this;
    }

    /**
     * Returns the Mime Type from the file binary.
     *
     * @param string $binary
     * @return string
     */
    private function getMimeTypeFromBinary($binary)
    {
        if ($this->_finfo === null)
        {
            $this->_finfo = new \finfo(FILEINFO_MIME_TYPE);
        }
        return $this->_finfo->buffer($binary);
    }

    /**
     * Embed a binary as an image in the message.
     *
     * @see \snapcms\mandrill\Message::$_images private attribute
     * @see \snapcms\mandrill\Message::getEmbeddedContent() getter
     *
     * @param string $content attachment file content.
     * @param array $options options for embed file. Valid options are:
     *
     * - fileName: name, which should be used to attach file.
     * - contentType: attached file MIME type.
     *
     * @return \snapcms\mandrill\Message
     */
    public function embedContent($content, array $options = array())
    {
        $purifiedOptions = is_array($options) ? $options : [];
        if (is_string($content) && strlen($content) !== 0 && strpos($this->getMimeTypeFromBinary($content), 'image') === 0)
        {
            $this->_images[] = [
                'name' => ArrayHelper::getValue($purifiedOptions, 'fileName', ('file_' . count($this->_images))),
                'type' => ArrayHelper::getValue($purifiedOptions, 'contentType', $this->getMimeTypeFromBinary($content)),
                'content' => base64_encode($content),
            ];
        }
        return $this;
    }

    /**
     * Taken from: https://github.com/nickcv-ln/yii2-mandrill
     */
    public function embed($fileName, array $options = array())
    {
        if (file_exists($fileName) && !is_dir($fileName) && strpos(FileHelper::getMimeType($fileName), 'image') === 0)
        {
            $purifiedOptions = [
                'fileName' => ArrayHelper::getValue($options, 'fileName', basename($fileName)),
                'contentType' => ArrayHelper::getValue($options, 'contentType', FileHelper::getMimeType($fileName)),
            ];
            $this->embedContent(file_get_contents($fileName), $purifiedOptions);
        }
        return $this;
    }

    /**
     * Returns the images array.
     *
     * @see \snapcms\mandrill\Message::$_images private attribute
     * @see \snapcms\mandrill\Message::embed() setter for file name
     * @see \snapcms\mandrill\Message::embedContent() setter for binary
     *
     * @return array list of embedded content
     */
    public function getEmbeddedContent()
    {
        return $this->_images;
    }

    /**
     * @inheritdoc
     */
    public function toString()
    {
        return $this->getSubject() . ' - Recipients:'
            . ' [TO] ' . implode('; ', $this->getTo())
            . ' [CC] ' . implode('; ', $this->getCc())
            . ' [BCC] ' . implode('; ', $this->getBcc());
    }

    /**
     * Gets the string rappresentation of Reply-To to be later used in the
     * email header.
     *
     * @return string
     */
    private function getReplyToString()
    {
        $addresses = [];
        foreach ($this->_replyTo as $key => $value)
        {
            if (is_string($key)) {
                $addresses[] = $value . ' <' . $key . '>';
            } else {
                $addresses[] = $value;
            }
        }
        return implode(';', $addresses);
    }

    /**
     * Used to validate any emails going into Mandrill
     * @param string $email
     * @return boolean Whether the supplied argurment is a valid email or not
     */
    protected function validateEmail($email)
    {
        $validator = new EmailValidator;
        return $validator->validate($email);
    }

    /**
     * Process and array of recipients in the format
     * `[[email => name], [email => name]]` 
     * or just an array of emails without the name
     * `[email, email, email]` 
     * @param type $type
     * @param type $emails
     */
    protected function setRecipients($type, $emails)
    {
        if (is_array($emails))
        {
            foreach ($emails as $email => $name)
            {
                if (is_numeric($email)) { //we have an array index, so the "$name" must be the email 
                    $this->setRecipient($type, $name);
                } else {
                    $this->setRecipient($type, $email, $name);
                }
            }
        } else {
            $this->setRecipient($type, $emails);
        }
    }

    /**
     * Set a single recipient ready to be sent.
     * @param string $type "to", "cc" or "bcc"
     * @param string $email
     * @param string $name
     * @throws \yii\base\Exception
     */
    protected function setRecipient($type, $email, $name = null)
    {
        $errorMessage = Yii::t('app', $this->emailErrorMessage, ['email' => $email, 'type' => $type]);
        $field = '_' . $type;
        if ($this->validateEmail($email)) {
            array_push($this->$field, $email);
        } else if ($this->throwErrors) {
            throw new \yii\base\Exception($errorMessage);
        } else {
            Yii::error($errorMessage, __METHOD__);
        }
        $this->_recipients[] = [
            'email' => $email,
            'name' => $name,
            'type' => $type,
        ];
    }

    /**
     * Returns the array used by the Mandrill Class to initialize a message
     * and submit it.
     *
     * @return array
     */
    public function getMandrillMessageArray()
    {
        $recipients = $this->_recipients;
        $htmlBody = $this->_htmlBody;
        
        //if dev environment only send it to the admin email and append the 
        //recipients it would have sent to, to the end of the email
        if(YII_ENV == 'dev') 
        {
            $htmlBody .= '<pre>' . print_r($this->_recipients, true) . '</pre>';
            
            $fromMail = Config::getData('general/site.admin_email');
            $fromName = Config::getData('general/site.admin_email_from');
            
            $recipients = [
                [
                    'email' => $fromMail,
                    'name' => $fromName,
                    'type' => 'to',
                ]
            ];
        }
        
        return [
            'headers' => [
                'Reply-To' => $this->getReplyToString(),
            ],
            'html' => $htmlBody,
            'text' => $this->_textBody,
            'subject' => $this->getSubject(),
            'from_email' => $this->_from,
            'from_name' => $this->_fromName,
            'to' => $recipients,
            'track_opens' => true,
            'track_clicks' => true,
            'tags' => $this->_tags,
            'attachments' => $this->_attachments,
            'images' => $this->_images,
            'global_merge_vars' => $this->_global_merge_vars,
            'merge_vars' => $this->_merge_vars,
        ];
    }

}
