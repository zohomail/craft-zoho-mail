<?php


 namespace zohomail\craftzohomail\mail;


use Craft;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use zohomail\craftzohomail\Helper\ZohoMailApi;

class ZohoMailTransport extends AbstractApiTransport
{
    private string $authtoken;

    private string $region = '';

    /**
     * @param string $key
     * @param HttpClientInterface|null $client
     * @param EventDispatcherInterface|null $dispatcher
     * @param LoggerInterface|null $logger
     */
    public function __construct(HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct($client, $dispatcher, $logger);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('zohomail+api://%s', $this->getEndpoint());
    }

    /**
     * @param SentMessage $sentMessage
     * @param Email $email
     * @param Envelope $envelope
     * @return ResponseInterface
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $zmailSettings = Craft::$app->getProjectConfig()->get("zohomail.settings");
        if(!isset($zmailSettings)) {
            throw new HttpTransportException('Configure Zoho Mail settings before proceed');
        }
        
        $accountId = $zmailSettings['account_id'];
        $fromName = $zmailSettings['from_name'];
        $fromAddress = $zmailSettings['from_address'];
        $allowedEmails = $zmailSettings['allowed_emails'];
        $zohoMailApi = new ZohoMailApi();
        $zohoMailApi->accountId = $accountId;
        $mail_data = $this->getPayload($email, $envelope,$fromAddress,$allowedEmails);
        if(!empty($email->getAttachments())){
            $attachmentRes = $zohoMailApi->uploadAttachment($email->getAttachments());
            $mail_data['attachments'] = json_decode($attachmentRes)->data;
        }
        
        $response = $zohoMailApi->sendZohoMail($mail_data);
        return $response;
        
    }

    /**
     * @return string|null
     */
    private function getEndpoint(): ?string
    {

        return "https://zohomail.".$this->domainMapping[$this->region].'/v1.1/email';
    }

    /**
     * @param Email $email
     * @param Envelope $envelope
     * @return array
     */
    private function getPayload(Email $email, Envelope $envelope,$fromEmail,$allowedEmails): array
    {
        $recipients = $this->getRecipients($email, $envelope);
        $toaddress = $this->getEmailDetailsByType($recipients,'to');
        $ccaddress = $this->getEmailDetailsByType($recipients,'cc');
        $bccaddress = $this->getEmailDetailsByType($recipients,'bcc');
        $attachmentJSONArr = array();
        $fromEmailDetail = ['address' => $fromEmail];
        if ('' !== $envelope->getSender()->getName()) {
            $fromEmailDetail['name'] = $envelope->getSender()->getName();
        }
        $payload['fromAddress'] = $fromEmail;
        $payload['subject'] = $email->getSubject();
        $payload['content'] = $email->getHtmlBody();
        $payload['mailFormat'] = 'html';
         
        if(isset($toaddress) && !empty($toaddress)) {
            $payload['toAddress'] =implode(",",$toaddress);
        }
       
        if(isset($ccaddress) && !empty($ccaddress)) {
            $payload['ccAddress'] = implode(",",$ccaddress);
        }
        if(isset($bccaddress) && !empty($bccaddress)) {
            $payload['bccAddress'] = implode(",",$bccaddress);
        }
        

        return $payload;
    }

 /**
     * @param Email $email
     * @param Envelope $envelope
     * @return array
     */
    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        $recipients = [];

        foreach ($envelope->getRecipients() as $recipient) {
            $type = 'to';

            if (\in_array($recipient, $email->getBcc(), true)) {
                $type = 'bcc';
            } elseif (\in_array($recipient, $email->getCc(), true)) {
                $type = 'cc';
            }

            $recipientPayload = [
                'email' => $recipient->toString(),
                'type' => $type,
            ];

            $recipients[] = $recipientPayload;
        }

        return $recipients;
    }
    protected function getEmailDetailsByType(array $recipients,string $type): array
    {
        $sendmailaddress = [];
        foreach ($recipients as $recipient) {
            if($type === $recipient['type']){
                
                $sendmailaddress[] = $recipient['email'];
            }
           
        }
        return $sendmailaddress;
    }


    
    public function setRegion(string $region): static
    {
        $this->region = $region;

        return $this;
    }

    /**
     * @param Address[] $addresses
     */
    private function prepareReplyAddresses(array $addresses,$allowedEmails): array
    {
        $recipients = [];
        

        foreach ($addresses as $address) {
            //if(in_array($address->getAddress(),$allowedEmails))
            {
                $recipients[] = [
                    'email' => $address->getAddress(),
                    'name' => $address->getName(),
                ];
                break;
            }
            
        }

        return $recipients;
    }

    public $domainMapping = [
		"zoho.com"          => "zoho.com",
		"zoho.eu"           => "zoho.eu", 
		"zoho.in"           => "zoho.in", 
		"zoho.com.cn"       => "zoho.com.cn",
		"zoho.com.au"       => "zoho.com.au",
		"zoho.jp"           => "zoho.jp",
		"zohocloud.ca"      => "zohocloud.ca",
		"zoho.sa"           => "zoho.sa"
    ];

   
}

