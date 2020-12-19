<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace ManishJoy\CreditPay\Model\Config\Source\Order\Status;

/**
 * Order Statuses source model
 */
class Credit extends \Magento\Sales\Model\Config\Source\Order\Status
{
    const CREDIT_STATUS_CODE = 'credit_status';
    const CREDIT_STATE_CODE = 'credit_state';
    /**
     * @var string
     */
    protected $_stateStatuses = self::CREDIT_STATE_CODE;
}
