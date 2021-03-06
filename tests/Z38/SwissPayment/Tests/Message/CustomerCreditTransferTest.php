<?php

namespace Z38\SwissPayment\Tests\Message;

use Z38\SwissPayment\Message\CustomerCreditTransfer;
use Z38\SwissPayment\TransactionInformation\BankCreditTransfer;
use Z38\SwissPayment\TransactionInformation\IS1CreditTransfer;
use Z38\SwissPayment\TransactionInformation\IS2CreditTransfer;
use Z38\SwissPayment\PaymentInformation\PaymentInformation;
use Z38\SwissPayment\BC;
use Z38\SwissPayment\BIC;
use Z38\SwissPayment\IBAN;
use Z38\SwissPayment\Money;
use Z38\SwissPayment\PostalAddress;
use Z38\SwissPayment\PostalAccount;
use Z38\SwissPayment\Tests\TestCase;

class CustomerCreditTransferTest extends TestCase
{
    const SCHEMA = 'pain.001.001.03.ch.02.xsd';
    const NS_URI_ROOT = 'http://www.six-interbank-clearing.com/de/';

    protected function buildMessage()
    {
        $transaction = new BankCreditTransfer(
            'instr-001',
            'e2e-001',
            new Money\CHF(130000), // CHF 1300.00
            'Muster Transport AG',
            new PostalAddress('Wiesenweg', '14b', '8058', 'Zürich-Flughafen'),
            new IBAN('CH51 0022 5225 9529 1301 C'),
            new BIC('UBSWCHZH80A')
        );

        $transaction2 = new IS1CreditTransfer(
            'instr-002',
            'e2e-002',
            new Money\CHF(30000), // CHF 300.00
            'Finanzverwaltung Stadt Musterhausen',
            new PostalAddress('Altstadt', '1a', '4998', 'Muserhausen'),
            new PostalAccount('80-151-4')
        );

        $transaction3 = new IS2CreditTransfer(
            'instr-003',
            'e2e-003',
            new Money\CHF(20000), // CHF 200.00
            'Druckerei Muster GmbH',
            new PostalAddress('Gartenstrasse', '61', '3000', 'Bern'),
            new IBAN('CH03 0900 0000 3054 1118 8'),
            'Musterbank AG',
            new PostalAccount('80-5928-4')
        );

        $iban4 = new IBAN('CH51 0022 5225 9529 1301 C');
        $transaction4 = new BankCreditTransfer(
            'instr-004',
            'e2e-004',
            new Money\CHF(30000), // CHF 300.00
            'Muster Transport AG',
            new PostalAddress('Wiesenweg', '14b', '8058', 'Zürich-Flughafen'),
            $iban4,
            BC::fromIBAN($iban4)
        );

        $payment = new PaymentInformation('payment-001', 'InnoMuster AG', new BIC('ZKBKCHZZ80A'), new IBAN('CH6600700110000204481'));
        $payment->addTransaction($transaction);
        $payment->addTransaction($transaction2);
        $payment->addTransaction($transaction3);
        $payment->addTransaction($transaction4);

        $message = new CustomerCreditTransfer('message-001', 'InnoMuster AG');
        $message->addPayment($payment);

        return $message;
    }

    public function testGroupHeader()
    {
        $xml = $this->buildMessage()->asXml();

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('pain001', self::NS_URI_ROOT.self::SCHEMA);

        $nbOfTxs = $xpath->query('//pain001:GrpHdr/pain001:NbOfTxs');
        $this->assertEquals('4', $nbOfTxs->item(0)->textContent);

        $ctrlSum = $xpath->query('//pain001:GrpHdr/pain001:CtrlSum');
        $this->assertEquals('2100.00', $ctrlSum->item(0)->textContent);
    }

    public function testSchemaValidation()
    {
        $xml = $this->buildMessage()->asXml();
        $schemaPath = __DIR__.'/../../../../'.self::SCHEMA;

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        libxml_use_internal_errors(true);
        $valid = $doc->schemaValidate($schemaPath);
        foreach (libxml_get_errors() as $error) {
            $this->fail($error->message);
        }
        $this->assertTrue($valid);
        libxml_clear_errors();
        libxml_use_internal_errors(false);
    }
}
