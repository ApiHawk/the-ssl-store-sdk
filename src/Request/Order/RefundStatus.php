<?php
/**
 * Created by IntelliJ IDEA.
 * User: nikolayyotsov
 * Date: 7/19/17
 * Time: 4:40 PM
 */

namespace SslStoreSdk\Request\Order;

use SslStoreSdk\Core\BaseRequest;

class RefundStatus extends BaseRequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $ResendEmail;
    public $RefundReason;
    public $RefundRequestID;
    public $ApproverMethod;
    public $DomainNames;
    public $SerialNumber;
}