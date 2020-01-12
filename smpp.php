<?php

class smpp {
  private $socket = 0;
  private $seq = 0;
  private $debug = 0;
  private $dataCoding = 0;
  private $timeout = 2;

  public function __construct($debug = 0){
      $this->debug = $debug;
  }

  public function sendPdu($id, $data) {

    // increment sequence
    $this->seq += 1;
    // PDU = PDU_header + PDU_content
    $pdu = pack('NNNN', strlen($data)+16, $id, 0, $this->seq) . $data;
    // send PDU
    fputs($this->socket, $pdu);

    // Get response length
    $data = fread($this->socket, 4);
    if($data == false) die("\nSend PDU: Connection closed!");
    $tmp = unpack('Nlength', $data);
    $commandLen = $tmp['length'];
    if($commandLen < 12) return;

    // Get response 
    $data = fread($this->socket, $commandLen - 4);
    $pdu = unpack('Nid/Nstatus/Nseq', $data);
    if($this->debug) print "\n< R PDU (id,status,seq): " .join(" ",$pdu) ;

    return $pdu;
  }

  public function open($host, $port, $systemId, $password) {
    $this->socket = fsockopen($host, $port, $errorNo, $errorStr, $this->timeout);
    if ($this->socket === false)
       die("$errorStr ($errorNo)<br />");
    if (function_exists('stream_set_timeout'))
       stream_set_timeout($this->socket, $this->timeout); // function exists for php4.3+
    if($this->debug) print "\n> Connected" ;

    $data  = sprintf("%s\0%s\0", $systemId, $password); // systemId, password 
    $data .= sprintf("%s\0%c", "", 0x34);  // system_type, interface_version
    $data .= sprintf("%c%c%s\0", 5, 0, ""); // addr_ton, addr_npi, address_range 

    $ret = $this->sendPdu(2, $data);
    if($this->debug) print "\n> Bind done!" ;

    return ($ret['status'] == 0);
  }

  public function submitSms($srcAddr, $dstAddr, $message, $optional='') {

    $data  = sprintf("%s\0", ""); // serviceType
    $data .= sprintf("%c%c%s\0", 5, 0, $srcAddr); // srcAddrTon, srcAddrNpi, srcAddr
    $data .= sprintf("%c%c%s\0", 1, 1, $dstAddr); // dstAddrTon, dstAddrNpi, dstAddr
    $data .= sprintf("%c%c%c", 0, 0, 0); // esm_class, protocol_id, priority_flag
    $data .= sprintf("%s\0%s\0", "", ""); // schedule_delivery_time, validity_period
    $data .= sprintf("%c%c", 0, 0); // registered_delivery, replace_if_present_flag
    $data .= sprintf("%c%c", $this->dataCoding, 0); // dataCoding, sm_default_msg_id
    $data .= sprintf("%c%s", strlen($message), $message); // smsLength, message
    $data .= $optional;

    $ret = $this->sendPdu(4, $data);
    return ($ret['status'] == 0);
  }

  public function close() {
    $ret = $this->sendPdu(6, "");
    fclose($this->socket);
    return true;
  }

  public function sendMessage($srcAddr, $dstAddr, $message, $utf8 = false, $flash = false) {

    if($utf8){
      $message = iconv("UTF-8", 'UTF-16BE', $message);
      $this->dataCoding = 0x08;
    }

    if($flash)
      $this->dataCoding = $this->dataCoding | 0x10;

    $size = strlen($message);
    if($utf8) $size += 20;

    if ($size < 160) { // Only one part :)
      $this->submitSms($srcAddr,$dstAddr,$message);
    } else { // Multipart
      $sar_msg_ref_num =  rand(1, 255);
      $sar_total_segments = ceil(strlen($message) / 130);

      for($sar_segment_seqnum = 1; $sar_segment_seqnum <= $sar_total_segments; $sar_segment_seqnum++) {
        $part = substr($message, 0 ,130);
        $message = substr($message, 130);

        $optional  = pack('nnn', 0x020C, 2, $sar_msg_ref_num);
        $optional .= pack('nnc', 0x020E, 1, $sar_total_segments);
        $optional .= pack('nnc', 0x020F, 1, $sar_segment_seqnum);

        $sms = $this->submitSms($srcAddr, $dstAddr, $part, $optional);
        if ($sms === false)
           return false;
      }
    }

   return true;
  }
}