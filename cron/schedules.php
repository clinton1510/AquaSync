<?php
// Run with PHP CLI from Windows Task Scheduler. Web execution is forbidden.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';
$lock=one("SELECT GET_LOCK('aquasync_scheduler',0) acquired");if(!$lock['acquired'])exit("Another scheduler is running.\n");
$failures=0;
try {
 foreach(rows("SELECT id FROM subscriptions WHERE status='Active' AND next_delivery_date<=CURDATE()+INTERVAL 1 DAY") as $candidate){
  try { $db->beginTransaction();$s=one('SELECT * FROM subscriptions WHERE id=? FOR UPDATE',[$candidate['id']]);
   if($s['status']==='Active'){
    $days=array_map('intval',explode(',',$s['selected_days']));$date=$s['next_delivery_date'];
    // Skip missed dates rather than backfilling deliveries in the past.
    if($date<date('Y-m-d'))$date=next_date($days,date('Y-m-d'),true);
    if($date<=date('Y-m-d',strtotime('+1 day'))){
     if(!one('SELECT id FROM orders WHERE subscription_id=? AND subscription_date=?',[$s['id'],$date])) create_order((int)$s['customer_id'],(int)$s['product_id'],(int)$s['quantity'],(int)$s['address_id'],$date.' '.substr($s['preferred_time'],0,5),$s['payment_method'],(int)$s['id'],$date);
     $date=next_date($days,$date);
    }
    q('UPDATE subscriptions SET next_delivery_date=? WHERE id=?',[$date,$s['id']]);
   }$db->commit();
  }catch(Throwable $ex){if($db->inTransaction())$db->rollBack();$failures++;fwrite(STDERR,'Subscription '.$candidate['id'].': '.$ex->getMessage()."\n");}
 }
 foreach(rows("SELECT id FROM users WHERE role='customer'") as $c){$p=prediction((int)$c['id']);if($p && $p['date']<=date('Y-m-d',strtotime('+2 days')))notify((int)$c['id'],'You may be running low on drinking water. Your estimated next refill date is '.$p['date'].'. This is an estimate.','refill-'.$c['id'].'-'.$p['date']);}
 foreach(rows("SELECT id,customer_id,order_number FROM orders WHERE DATE(scheduled_at)=CURDATE()+INTERVAL 1 DAY AND status NOT IN ('Delivered','Cancelled')") as $o)notify((int)$o['customer_id'],'Your scheduled water delivery '.$o['order_number'].' is tomorrow.','scheduled-'.$o['id']);
}finally{q("SELECT RELEASE_LOCK('aquasync_scheduler')");}
echo "Scheduler complete; failures: $failures\n";exit($failures?1:0);
