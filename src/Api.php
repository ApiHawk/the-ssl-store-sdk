<?php
/**
 * Created by IntelliJ IDEA.
 * User: nikolayyotsov
 * Date: 7/19/17
 * Time: 5:51 PM
 */

namespace SslStoreSdk;

use SslStoreSdk\Core\ApiRequest;
use SslStoreSdk\Core\ApiResponse;
use SslStoreSdk\Core\CurlResponse;
use SslStoreSdk\Request\Order\ChangeApproverEmail;
use SslStoreSdk\Request\Order\Download;
use SslStoreSdk\Request\Order\InviteOrder;
use SslStoreSdk\Request\Order\NewOrder;
use SslStoreSdk\Request\Order\NewOrderRequestFree;
use SslStoreSdk\Request\Order\OrderAgreement;
use SslStoreSdk\Request\Order\Refund;
use SslStoreSdk\Request\Order\RefundStatus;
use SslStoreSdk\Request\Order\ReIssue;
use SslStoreSdk\Request\Order\Resend;
use SslStoreSdk\Request\Order\Status;
use SslStoreSdk\Request\Order\Validate;
use SslStoreSdk\Request\Setting\CancelNotification;
use SslStoreSdk\Request\Setting\SetOrderCallBack;
use SslStoreSdk\Request\Setting\SetPriceCallBack;
use SslStoreSdk\Request\Setting\SetTemplate;
use SslStoreSdk\Request\User\Activate;
use SslStoreSdk\Request\User\Add;
use SslStoreSdk\Request\User\Deactivate;
use SslStoreSdk\Request\User\Query;
use SslStoreSdk\Response\Csr;
use SslStoreSdk\Response\FreeClaimfree;
use SslStoreSdk\Response\FreeCuinfo;
use SslStoreSdk\Response\HealthValidate;
use SslStoreSdk\Response\Order\Agreement;
use SslStoreSdk\Response\Order\ApproverList;
use SslStoreSdk\Response\Order\DownloadZip;
use SslStoreSdk\Response\Order\ModifiedSummary;
use SslStoreSdk\Response\Order\Order;
use SslStoreSdk\Response\Order\Pmr;
use SslStoreSdk\Response\Order\VulnerabilityScan;
use SslStoreSdk\Response\SslValidation;
use SslStoreSdk\Response\User\AccountDetail;
use SslStoreSdk\Response\User\NewUser;
use SslStoreSdk\Response\User\SubUser;
use SslStoreSdk\Response\Whois;

/**
 * Class SslStoreApi
 *
 * @package SslStoreSdk
 */
class Api
{
    public static $API_MODE_LIVE   = 'LIVE';
    public static $API_MODE_TEST   = 'TEST';
    public static $LOG_ALLAPICALLS = false;

    private $_apimode              = 'TEST';
    private $_partnerCode          = '';
    private $_authToken            = '';
    private $_token                = '';
    private $_tokenID              = '';
    private $_tokenCode            = '';
    private $_IsUsedForTokenSystem = false;
    private $_userAgent            = '';
    private $_IPAddress            = '';

    function __construct($partnerCode, $authToken, $token, $tokenID, $tokenCode,
        $IsUsedForTokenSystem, $apimode, $userAgent = 'PHP SDK', $ipAddress = ''
    ) {
        $this->EnsurePHPVersion();
        $this->_apimode              = $apimode;
        $this->_partnerCode          = $partnerCode;
        $this->_authToken            = $authToken;
        $this->_token                = $token;
        $this->_tokenID              = $tokenID;
        $this->_tokenCode            = $tokenCode;
        $this->_IsUsedForTokenSystem = $IsUsedForTokenSystem;
        $this->_userAgent            = $userAgent;
        $this->_IPAddress            = $ipAddress;
    }

    public function EnsurePHPVersion()
    {
        if (floatval(phpversion()) < 5.2) {
            throw new \Exception(
                'Not Supported version of PHP. Requires atleast 5.2 or greater version of PHP.'
            );
        }
    }

    private function getAPIRequest()
    {
        $AuthRequest                       = new ApiRequest();
        $AuthRequest->AuthToken            = $this->_authToken;
        $AuthRequest->PartnerCode          = $this->_partnerCode;
        $AuthRequest->UserAgent            = $this->_userAgent;
        $AuthRequest->Token                = $this->_token;
        $AuthRequest->TokenID              = $this->_tokenID;
        $AuthRequest->TokenCode            = $this->_tokenCode;
        $AuthRequest->IsUsedForTokenSystem = $this->_IsUsedForTokenSystem;
        $AuthRequest->ReplayToken          = uniqid('SSLSTORE-PHP');
        $AuthRequest->IPAddress            = $this->_IPAddress;

        return $AuthRequest;
    }

    private function cloneObjectFromJson($obj, $jsonobj)
    {
        if ($jsonobj != null && is_object($jsonobj)) {
            foreach ($jsonobj AS $key => $val) {
                $obj->{$key} = $val;
            }

            return $obj;
        } else {
            return $jsonobj;
        } //No need to map as it's a scalar value
    }

    private function getCURL($url, $method, $message = '')
    {
        $ch = curl_init();
        if (!$ch) {
            die("Couldn't initialize a cURL handle");
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($message != '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        } //Append POST messages
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER,
            ["Content-Type: application/json; charset=utf-8"]
        );

        return $ch;
    }

    private function getCURLResponse($curl)
    {
        $returnresp           = new CurlResponse();
        $returnresp->response = curl_exec($curl);
        if (curl_errno($curl)) {
            $returnresp->error = curl_error($curl);
        }
        $returnresp->info = curl_getinfo($curl);
        curl_close($curl); // close cURL handler

        return $returnresp; //Return Result
    }

    private function postToCurl($url, $requestData, $responseData,
        $HttpMethod = 'POST'
    ) {
        $logid = uniqid('api-without-token-'); //for calls without ID
        if (isset($requestData->AuthRequest)) {
            $requestData->AuthRequest = $this->getAPIRequest();
            $logid                    = $requestData->AuthRequest->ReplayToken;
        }
        $msg = '';
        /*echo "<pre>";
        print_r($requestData);
        die();*/
        if ($requestData != null) {
            $msg = json_encode($requestData);
        } //SET JSON FORMAT if not null
        $curl     = $this->getCURL($url, $HttpMethod, $msg);
        $response = $this->getCURLResponse($curl);

        if (Api::$LOG_ALLAPICALLS) {
            $requestfile  = $logid . '-request.json';
            $responsefile = $logid . '-response.json';
            file_put_contents($requestfile, $msg);
            file_put_contents($responsefile, $response);
        }

        if ($response->error == '') {
            $respobj = json_decode($response->response);

            if ($responseData
                != null
                && $respobj
            ) //Indicates if Casting required to a class type
            {
                $result = $this->cloneObjectFromJson($responseData, $respobj);
            } else {
                $responseData->isError = true;
                $responseData->Message = ['Error' => $response->response ??
                    'Empty response!'];

                $errorWithMsg               = new \stdClass();
                $errorWithMsg->isError      = true;
                $errorWithMsg->Message      = ['Error' => $response->response ??
                    'Empty response!'];
                $responseData->AuthResponse = $errorWithMsg;

                $result = $responseData;
            }

            if (isset($result->AuthRequest)) {
                if ($result->AuthResponse->ReplayToken
                    != $requestData->AuthRequest->ReplayToken
                ) {
                    $result = $responseData;
                }
            }

            return $result;
        } else {

            $responseData->AuthResponse->isError = true;
            $responseData->AuthResponse->Message = [$response->error];

            return $responseData;
        }
    }

    public function getURL()
    {
        if (strtoupper($this->_apimode) == 'LIVE') {
            return 'https://api.thesslstore.com/rest';
        } else {
            return 'https://sandbox-wbapi.thesslstore.com/rest';
        }
    }

    /**
     * @param \SslStoreSdk\Request\Csr $csr_request
     *
     * @return Csr
     */
    public function csr($csr_request
    ) {
        $url                 = $this->getURL() . '/csr/';
        $csrreq              = new \SslStoreSdk\Request\Csr();
        $csrreq->ProductCode = $csr_request->ProductCode;
        $csrreq->CSR         = str_ireplace("\r\n", '', $csr_request->CSR);
        $csrresp             = new Csr();

        return $this->postToCurl($url, $csrreq, $csrresp);
    }

    /**
     * @param \SslStoreSdk\Request\SslValidation $ssl_validation_request
     *
     * @return SslValidation
     */
    public function ssl_validation($ssl_validation_request
    ) {
        $url  = $this->getURL() . '/sslchecker/';
        $resp = new SslValidation();

        return $this->postToCurl($url, $ssl_validation_request, $resp);
    }

    /**
     * @param \SslStoreSdk\Request\Whois $whois_request
     *
     * @return Whois
     */
    public function whois($whois_request
    ) {
        $url  = $this->getURL() . '/whois/';
        $resp = new Whois();

        return $this->postToCurl($url, $whois_request, $resp);
    }

    /**
     * @param \SslStoreSdk\Request\FreeClaimFree $free_claimfree_request
     *
     * @return FreeClaimfree
     */
    public function free_claimfree($free_claimfree_request
    ) {
        $url  = $this->getURL() . '/free/claimfree/';
        $resp = new FreeClaimfree();

        return $this->postToCurl($url, $free_claimfree_request, $resp);
    }

    /**
     * @param \SslStoreSdk\Request\FreeCuinfo $free_cuinfo_request
     *
     * @return FreeCuinfo
     */
    public function free_cuinfo($free_cuinfo_request
    ) {
        $url  = $this->getURL() . '/free/cuinfo/';
        $resp = new FreeCuinfo();

        return $this->postToCurl($url, $free_cuinfo_request, $resp);
    }

    /**
     * @return ApiResponse
     */
    public function health_status()
    {
        $url  = $this->getURL() . '/health/status/';
        $resp = new ApiResponse();

        return $this->postToCurl($url, null, $resp, 'GET');
    }

    /**
     * @param \SslStoreSdk\Request\HealthValidate $health_validate_request
     *
     * @return HealthValidate
     */
    public function health_validate($health_validate_request
    ) {
        $url                                  = $this->getURL()
            . '/health/validate/';
        $resp                                 = new HealthValidate();
        $apidetails                           = $this->getAPIRequest();
        $health_validate_request->AuthToken   = $apidetails->AuthToken;
        $health_validate_request->PartnerCode = $apidetails->PartnerCode;
        $health_validate_request->UserAgent   = $apidetails->UserAgent;
        $health_validate_request->ReplayToken = $apidetails->ReplayToken;

        return $this->postToCurl($url, $health_validate_request, $resp);
    }

    /**
     * @param \SslStoreSdk\Request\HealthValidateToken $health_validate_token_request
     *
     * @return HealthValidate
     */
    public function health_validate_token($health_validate_token_request
    ) {
        $url                                                 = $this->getURL()
            . '/health/validatetoken/';
        $resp
                                                             = new HealthValidate(
        );
        $apidetails
                                                             = $this->getAPIRequest(
        );
        $health_validate_token_request->IsUsedForTokenSystem = true;
        $health_validate_token_request->UserAgent
                                                             = $apidetails->UserAgent;
        $health_validate_token_request->ReplayToken
                                                             = $apidetails->ReplayToken;

        return $this->postToCurl($url, $health_validate_token_request, $resp);
    }

    /**
     * @param OrderAgreement $order_agreement_request
     *
     * @return string
     */
    public function order_agreement($order_agreement_request
    ) {
        $url  = $this->getURL() . '/order/agreement/';
        $resp = new Agreement();

        return $this->postToCurl($url, $order_agreement_request, $resp);
    }

    /**
     * @param NewOrder $order_approverlist_request
     *
     * @return ApproverList
     */
    public function order_approverlist($order_approverlist_request
    ) {
        $url  = $this->getURL() . '/order/approverlist/';
        $resp = new ApproverList();

        return $this->postToCurl($url, $order_approverlist_request, $resp);
    }

    /**
     * @param Download $order_download_request
     *
     * @return \SslStoreSdk\Response\Order\Download
     */
    public function order_download($order_download_request
    ) {
        $url  = $this->getURL() . '/order/download/';
        $resp = new Download();

        return $this->postToCurl($url, $order_download_request, $resp);
    }

    /**
     * @param Download $order_download_request
     *
     * @return DownloadZip
     */
    public function order_download_zip($order_download_request
    ) {
        $url  = $this->getURL() . '/order/downloadaszip/';
        $resp = new DownloadZip();

        return $this->postToCurl($url, $order_download_request, $resp);
    }


    /**
     * @param InviteOrder $order_inviteorder_request
     *
     * @return Order
     */
    public function order_inviteorder($order_inviteorder_request
    ) {
        $url  = $this->getURL() . '/order/inviteorder/';
        $resp = new Order();

        return $this->postToCurl($url, $order_inviteorder_request, $resp);
    }

    /**
     * @param NewOrder $order_neworder_request
     *
     * @return Order
     */
    public function order_neworder($order_neworder_request
    ) {
        $url  = $this->getURL() . '/order/neworder/';
        $resp = new Order();

        return $this->postToCurl($url, $order_neworder_request, $resp);
    }

    /**
     * @param NewOrderRequestFree $order_neworder_request
     *
     * @return Order
     */
    public function order_midterm_upgrade($order_neworder_request
    ) {
        $url  = $this->getURL() . '/order/midtermupgrade/';
        $resp = new Order();

        return $this->postToCurl($url, $order_neworder_request, $resp);
    }

    /**  Should return array(order_response())
     *
     * @param \SslStoreSdk\Request\Order\Query $order_query_request
     *
     * @return object
     */
    public function order_query($order_query_request
    ) {
        $url  = $this->getURL() . '/order/query/';
        $resp = new \SslStoreSdk\Response\Order\Query();

        return $this->postToCurl($url, $order_query_request, $resp);
    }

    /**  Should return array(order_modified_summary_response())
     *
     * @param \SslStoreSdk\Request\Order\ModifiedSummary $order_modified_summary_request
     *
     * @return object
     */
    public function order_modified_summary($order_modified_summary_request
    ) {
        $url  = $this->getURL() . '/order/getmodifiedorderssummary/';
        $resp = new ModifiedSummary();

        return $this->postToCurl($url, $order_modified_summary_request, $resp);
    }

    /**
     * @param \SslStoreSdk\Request\Order\VulnerabilityScan $order_refundrequest_request
     *
     * @return ApiResponse
     */
    public function order_certificaterevokerequest($order_certificaterevokerequest_request
    ) {
        $url  = $this->getURL() . '/order/certificaterevokerequest/';
        $resp = new ApiResponse();

        return $this->postToCurl(
            $url, $order_certificaterevokerequest_request, $resp
        );
    }

    /**
     * @param \SslStoreSdk\Request\Order\VulnerabilityScan $order_refundrequest_request
     *
     * @return VulnerabilityScan
     */
    public function order_vulnerabilityscanrequest($order_vulnerabilityscanrequest_request
    ) {
        $url  = $this->getURL() . '/order/vulnerabilityscanrequest/';
        $resp = new VulnerabilityScan();

        return $this->postToCurl(
            $url, $order_vulnerabilityscanrequest_request, $resp
        );
    }

    /**
     * @param Refund $order_refundrequest_request
     *
     * @return Order
     */
    public function order_refundrequest($order_refundrequest_request
    ) {
        $url  = $this->getURL() . '/order/refundrequest/';
        $resp = new Order();

        return $this->postToCurl($url, $order_refundrequest_request, $resp);
    }

    /**
     * @param RefundStatus $order_refundstatus_request
     *
     * @return Order
     */
    public function order_refundstatus($order_refundstatus_request
    ) {
        $url  = $this->getURL() . '/order/refundstatus/';
        $resp = new Order();

        return $this->postToCurl($url, $order_refundstatus_request, $resp);
    }

    /**
     * @param ReIssue $order_reissue_request
     *
     * @return Order
     */
    public function order_reissue($order_reissue_request
    ) {
        $url  = $this->getURL() . '/order/reissue/';
        $resp = new Order();

        return $this->postToCurl($url, $order_reissue_request, $resp);
    }

    /**
     * @param ChangeApproverEmail $order_changeapproveremail_request
     *
     * @return ApiResponse
     */
    public function order_changeapproveremail($order_changeapproveremail_request
    ) {
        $url  = $this->getURL() . '/order/changeapproveremail/';
        $resp = new ApiResponse();

        return $this->postToCurl(
            $url, $order_changeapproveremail_request, $resp
        );
    }


    /**
     * @param Resend $order_resend_request
     *
     * @return ApiResponse
     */
    public function order_resend($order_resend_request
    ) {
        $url  = $this->getURL() . '/order/resend/';
        $resp = new ApiResponse();

        return $this->postToCurl($url, $order_resend_request, $resp);
    }

    /**
     * @param Status $order_status_request
     *
     * @return Order
     */
    public function order_status($order_status_request
    ) {
        $url  = $this->getURL() . '/order/status/';
        $resp = new Order();

        return $this->postToCurl($url, $order_status_request, $resp);
    }


    /**
     * @param Validate $order_validate_request
     *
     * @return apiresponse
     */
    public function order_validate($order_validate_request
    ) {
        $url  = $this->getURL() . '/order/validate/';
        $resp = new ApiResponse();

        return $this->postToCurl($url, $order_validate_request, $resp);
    }

    /**
     * @param \SslStoreSdk\Request\Order\Pmr $order_pmr_request
     *
     * @return apiresponse
     */
    public function order_pmr($order_pmr_request
    ) {
        $url  = $this->getURL() . '/order/pmrrequest/';
        $resp = new Pmr();

        return $this->postToCurl($url, $order_pmr_request, $resp);

    }

    /**
     * @param \SslStoreSdk\Request\Product\Query $product_query_request
     *
     * @return object
     */
    public function product_query($product_query_request
    ) {
        $url  = $this->getURL() . '/product/query/';
        $resp = new ApiResponse();

        return $this->postToCurl($url, $product_query_request, $resp);
    }

    /**
     * @param SetOrderCallBack $setting_setordercallback_request
     *
     * @return apiresponse
     */
    public function setting_setordercallback($setting_setordercallback_request
    ) {
        $url  = $this->getURL() . '/setting/setordercallback/';
        $resp = new ApiResponse();

        return $this->postToCurl(
            $url, $setting_setordercallback_request, $resp
        );
    }

    /**
     * @param SetPriceCallBack $setting_setpricecallback_request
     *
     * @return apiresponse
     */
    public function setting_setpricecallback($setting_setpricecallback_request
    ) {
        $url  = $this->getURL() . '/setting/setpricecallback/';
        $resp = new ApiResponse();

        return $this->postToCurl(
            $url, $setting_setpricecallback_request, $resp
        );
    }

    /**
     * @param SetTemplate $setting_settemplate_request
     *
     * @return apiresponse
     */
    public function setting_settemplate($setting_settemplate_request
    ) {
        $url  = $this->getURL() . '/setting/settemplate/';
        $resp = new ApiResponse();

        return $this->postToCurl($url, $setting_settemplate_request, $resp);
    }

    /**
     * @param CancelNotification $setting_cancelnotification_request
     *
     * @return ApiResponse
     */
    public function setting_setcancelnotification($setting_cancelnotification_request
    ) {
        $url  = $this->getURL() . '/setting/cancelnotification/';
        $resp = new ApiResponse();

        return $this->postToCurl(
            $url, $setting_cancelnotification_request, $resp
        );
    }

    /**
     * @param Add $user_add_request
     *
     * @return SubUser
     */
    public function user_add($user_add_request
    ) {
        $url  = $this->getURL() . '/user/add/';
        $resp = new SubUser();

        return $this->postToCurl($url, $user_add_request, $resp);
    }

    /**
     * @param Activate $user_activate_request
     *
     * @return SubUser
     */
    public function user_activate($user_activate_request
    ) {

        $url  = $this->getURL() . '/user/activate/';
        $resp = new SubUser();

        return $this->postToCurl($url, $user_activate_request, $resp);
    }

    /**
     * @param Deactivate $user_deactivate_request
     *
     * @return SubUser
     */
    public function user_deactivate($user_deactivate_request
    ) {
        $url  = $this->getURL() . '/user/deactivate/';
        $resp = new SubUser();

        return $this->postToCurl($url, $user_deactivate_request, $resp);
    }

    /**
     * @param Query $user_query_request
     *
     * @return object
     */
    public function user_query($user_query_request
    ) {
        $url  = $this->getURL() . '/user/query/';
        $resp = new \SslStoreSdk\Response\User\Query();

        return $this->postToCurl($url, $user_query_request, $resp);
    }

    /**
     * @param \SslStoreSdk\Request\User\NewUser $user_newuser_request
     *
     * @return object
     */
    public function user_newuser($user_newuser_request
    ) {
        $url  = $this->getURL() . '/user/newuser/';
        $resp = new NewUser();

        return $this->postToCurl($url, $user_newuser_request, $resp);
    }

    /**
     * @param \SslStoreSdk\Request\User\AccountDetail $user_account_detail_request
     *
     * @return \SslStoreSdk\Response\User\AccountDetail
     */
    public function user_account_detail($user_account_detail_request
    ) {
        $url        = $this->getURL() . '/user/accountdetail/';
        $resp       = new AccountDetail();
        $apidetails = $this->getAPIRequest();

        $user_account_detail_request->PartnerCode = $apidetails->PartnerCode;
        $user_account_detail_request->AuthToken   = $apidetails->AuthToken;
        $user_account_detail_request->ReplayToken = $apidetails->ReplayToken;
        $user_account_detail_request->UserAgent   = $apidetails->UserAgent;
        $user_account_detail_request->IPAddress   = $apidetails->IPAddress;

        return $this->postToCurl($url, $user_account_detail_request, $resp);
    }
}
