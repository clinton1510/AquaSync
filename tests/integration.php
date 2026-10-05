<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/actions.php';
$testDb='aquasync_test_'.bin2hex(random_bytes(6));$assertions=0;
function verify(bool $ok,string $message): void {global $assertions;if(!$ok)throw new RuntimeException($message);$assertions++;}
function action(array $u,string $action,array $data=[]): void {$_POST=array_merge(['csrf'=>'test-token','action'=>$action],$data);handle_action($u);}
function rejected(callable $fn,string $message): void {try{$fn();}catch(RuntimeException $e){verify(true,$message);return;}throw new RuntimeException($message);}
$_SESSION=['csrf'=>'test-token'];
try {
 $sql=str_replace('aquasync_db',$testDb,file_get_contents(dirname(__DIR__).'/aquasync_db.sql'));
 foreach(explode(';',$sql) as $statement)if(trim($statement))$db->exec($statement);
 $customer=one('SELECT * FROM users WHERE id=2');$admin=one('SELECT * FROM users WHERE id=1');$rider=one('SELECT * FROM users WHERE id=3');
 verify(password_verify('AquaSync123!',$customer['password']),'Seed password');verify(prediction(2)['days']===4.0,'Refill interval');verify(abs(haversine(0,0,0,1)-111.195)<0.1,'Haversine');
 action($customer,'order',['product_id'=>1,'quantity'=>3,'address_id'=>1,'scheduled_at'=>date('Y-m-d\T10:00',strtotime('+2 days')),'payment_method'=>'GCash']);$id=(int)$_SESSION['new_order'];$order=one('SELECT * FROM orders WHERE id=?',[$id]);verify((float)$order['total']===120.0,'Server pricing');verify((bool)preg_match('/^AQ-\d{8}-\d{4,}$/',$order['order_number']),'Order number');
 rejected(fn()=>action($customer,'assign',['id'=>$id,'rider_id'=>3]),'Customer assignment denied');rejected(fn()=>action($rider,'status',['id'=>$id,'status'=>'Delivered']),'Unassigned rider denied');
 $db->beginTransaction();rejected(fn()=>create_order(1,1,2,1,date('Y-m-d H:i',strtotime('+1 day')),'COD'),'Address ownership');$db->rollBack();
 action($customer,'reference',['id'=>$id,'reference'=>'TEST-GCASH-123']);verify(one('SELECT payment_reference FROM payments WHERE order_id=?',[$id])['payment_reference']==='TEST-GCASH-123','Reference');
 action($admin,'schedule',['id'=>$id,'scheduled_at'=>date('Y-m-d\T11:00',strtotime('+2 days'))]);verify(date('H:i',strtotime(one('SELECT scheduled_at FROM orders WHERE id=?',[$id])['scheduled_at']))==='11:00','Admin delivery scheduling');
 action($admin,'status',['id'=>$id,'status'=>'Confirmed']);action($admin,'status',['id'=>$id,'status'=>'Preparing']);action($admin,'assign',['id'=>$id,'rider_id'=>3]);
 rejected(fn()=>action($rider,'status',['id'=>$id,'status'=>'Out for Delivery']),'Pickup required');action($rider,'status',['id'=>$id,'status'=>'Picked Up']);action($rider,'location',['latitude'=>7.05,'longitude'=>125.6]);action($rider,'status',['id'=>$id,'status'=>'Out for Delivery']);action($rider,'status',['id'=>$id,'status'=>'Delivered']);
 verify(one('SELECT status FROM orders WHERE id=?',[$id])['status']==='Delivered','Delivery completed');action($admin,'payment',['id'=>$id,'payment_status'=>'Paid']);verify(one('SELECT payment_status FROM payments WHERE order_id=?',[$id])['payment_status']==='Paid','Payment verification');
 action($customer,'feedback',['id'=>$id,'rating'=>5,'comment'=>'Great service']);verify((int)one('SELECT rating FROM feedback WHERE order_id=?',[$id])['rating']===5,'Feedback saved');
 $days=[2,5];$start=date('Y-m-d');action($customer,'subscription',['product_id'=>1,'address_id'=>1,'quantity'=>2,'days'=>$days,'start_date'=>$start,'preferred_time'=>'09:00','payment_method'=>'COD']);$sub=one('SELECT * FROM subscriptions ORDER BY id DESC LIMIT 1');verify(in_array((int)date('N',strtotime($sub['next_delivery_date'])),$days,true),'Subscription weekdays');action($customer,'subscription_status',['id'=>$sub['id'],'status'=>'Paused']);action($customer,'subscription_status',['id'=>$sub['id'],'status'=>'Active']);action($customer,'subscription_status',['id'=>$sub['id'],'status'=>'Cancelled']);rejected(fn()=>action($customer,'subscription_status',['id'=>$sub['id'],'status'=>'Active']),'Cancelled schedule cannot resume');
 action($customer,'address',['address'=>'Integration address','barangay'=>'Matina','latitude'=>'7.1','longitude'=>'125.5']);$aid=(int)one('SELECT MAX(id) id FROM addresses')['id'];action($customer,'address',['id'=>$aid,'address'=>'Updated address','barangay'=>'Bucana','latitude'=>'','longitude'=>'']);verify(one('SELECT address FROM addresses WHERE id=?',[$aid])['address']==='Updated address','Address update');action($customer,'address_delete',['id'=>$aid]);verify(one('SELECT id FROM addresses WHERE id=?',[$aid])===null,'Address deletion');
 action($customer,'subscription',['product_id'=>1,'address_id'=>1,'quantity'=>2,'days'=>[(int)date('N')],'start_date'=>date('Y-m-d'),'preferred_time'=>'09:00','payment_method'=>'COD']);$due=(int)one('SELECT MAX(id) id FROM subscriptions')['id'];
 putenv('DB_NAME='.$testDb);
 for($run=0;$run<2;$run++){$process=proc_open([PHP_BINARY,dirname(__DIR__).'/cron/schedules.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);verify(proc_close($process)===0,'Scheduler failed: '.$out.$err);}
 verify((int)one('SELECT COUNT(*) n FROM orders WHERE subscription_id=?',[$due])['n']===1,'Scheduler idempotency');putenv('DB_NAME');
 verify((int)one('SELECT COUNT(*) n FROM notifications WHERE user_id=2')['n']>=5,'Status notifications');action($customer,'read');verify((int)one('SELECT COUNT(*) n FROM notifications WHERE user_id=2 AND read_at IS NULL')['n']===0,'Mark notifications read');
 echo "PASS: $assertions integration assertions. Isolated test data removed.\n";
}finally{if($db->inTransaction())$db->rollBack();$db->exec('USE aquasync_db');$db->exec('DROP DATABASE `'.$testDb.'`');}
