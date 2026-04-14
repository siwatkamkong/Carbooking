<?php
// Database connection
$host='localhost'; $db='car_booking'; $user='root'; $pass='W@tt7425j4636';
try{
    $pdo=new PDO("mysql:host=$host;dbname=$db;charset=utf8",$user,$pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
}catch(PDOException $e){
    die("Connection failed: ".$e->getMessage());
}


$error=''; $success='';
$dateInput=date('d/m/').(date('Y')+543);  //  พ.ศ.
$employeeId='';

// โหลดข้อมูลเดิมถ้ามี employee_id ผ่าน GET
if(isset($_GET['employee_id'])&&preg_match('/^\d{8}$/',$_GET['employee_id'])){
    $employeeId=$_GET['employee_id'];
    $stmt=$pdo->prepare("SELECT license_expiry_date FROM drivers_license WHERE employee_id=?");
    $stmt->execute([$employeeId]);
    if($row=$stmt->fetch(PDO::FETCH_ASSOC)){
        [$y,$m,$d]=explode('-',$row['license_expiry_date']);
        $dateInput=sprintf('%02d/%02d/%04d',$d,$m,$y);
    }
}

// ประมวลผลเมื่อกด Submit

if($_SERVER['REQUEST_METHOD']==='POST'){
    $employeeId=trim($_POST['employee_id']??'');
    $dateInput =trim($_POST['work_date']   ??'');

    if(!preg_match('/^\d{8}$/',$employeeId)){
        $error='รหัสพนักงานต้องเป็นตัวเลข 8 หลัก';
    }elseif(!preg_match('/^\d{2}\/\d{2}\/\d{4}$/',$dateInput)){
        $error='รูปแบบวันที่ต้องเป็น DD/MM/YYYY';
    }else{
        [$d,$m,$y]=explode('/',$dateInput);
        if(!checkdate((int)$m,(int)$d,(int)$y)){
            $error='วันที่ไม่ถูกต้อง';
        }else{
            $work_date=sprintf('%04d-%02d-%02d',$y,$m,$d);
            try{
                $pdo->beginTransaction();
                $pdo->prepare(
                    "INSERT INTO drivers_license (employee_id,license_expiry_date)
                     VALUES (:id,:d)
                     ON DUPLICATE KEY UPDATE license_expiry_date=VALUES(license_expiry_date)"
                )->execute([':id'=>$employeeId,':d'=>$work_date]);
                $pdo->commit();
                /* PRG pattern – refresh */
                header('Location: '.$_SERVER['PHP_SELF'].'?saved=1');
                exit;
            }catch(Exception $e){
                $pdo->rollBack();
                $error='เกิดข้อผิดพลาด: '.$e->getMessage();
            }
        }
    }
}

// ----------  API โหมด AJAX (quick search + pagination) ----------

if(isset($_GET['ajax'])){
    $term =trim($_GET['search'] ??'');
    $page =max(1,(int)($_GET['page']??1));
    $limit=10;
    $offset=($page-1)*$limit;

    $where="WHERE employee_id LIKE :kw";
    $count=$pdo->prepare("SELECT COUNT(*) FROM drivers_license $where");
    $count->bindValue(':kw',"%$term%",PDO::PARAM_STR);
    $count->execute();
    $total=$count->fetchColumn();
    $totalPages=max(1,ceil($total/$limit));

    $q=$pdo->prepare(
        "SELECT employee_id,license_expiry_date
         FROM drivers_license
         $where
         ORDER BY employee_id
         LIMIT :l OFFSET :o"
    );
    $q->bindValue(':kw',"%$term%",PDO::PARAM_STR);
    $q->bindValue(':l',$limit,PDO::PARAM_INT);
    $q->bindValue(':o',$offset,PDO::PARAM_INT);
    $q->execute();

    header('Content-Type:application/json;charset=utf-8');
    echo json_encode([
        'rows'=>$q->fetchAll(PDO::FETCH_ASSOC),
        'page'=>$page,
        'totalPages'=>$totalPages
    ]);
    exit;
}

// โหมดโหลดเต็มหน้า (มี PHP pagination เพื่อ fallback)
if(isset($_GET['saved'])) $success='บันทึกข้อมูลสำเร็จแล้ว';

$search    =trim($_GET['search']??'');
$page      =max(1,(int)($_GET['page']??1));
$limit     =10;
$offset    =($page-1)*$limit;
$where_sql ='';
$params    =[];

if($search!==''){
    $where_sql="WHERE employee_id LIKE :s";
    $params[':s']="%{$search}%";
}

$c=$pdo->prepare("SELECT COUNT(*) FROM drivers_license $where_sql");
$c->execute($params);
$totalRows =$c->fetchColumn();
$totalPages=max(1,ceil($totalRows/$limit));

$list=$pdo->prepare(
    "SELECT employee_id,license_expiry_date
     FROM drivers_license
     $where_sql
     ORDER BY employee_id
     LIMIT :l OFFSET :o"
);
if($search!=='') $list->bindValue(':s',$params[':s'],PDO::PARAM_STR);
$list->bindValue(':l',$limit,PDO::PARAM_INT);
$list->bindValue(':o',$offset,PDO::PARAM_INT);
$list->execute();
$rows=$list->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ฟอร์มกรอกข้อมูลพนักงาน</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.min.css"/>
<style>
body{font-family:'Kanit',sans-serif;background:#eef2f6;margin:0;display:flex;justify-content:center;padding:40px}
.container{background:#fff;padding:40px;max-width:650px;width:100%;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1)}
h2{text-align:center;margin-bottom:24px;color:#333}
.form-group{margin-bottom:20px}
label{display:block;margin-bottom:8px;font-weight:500;color:#555}
input[type=text]{width:100%;padding:12px;border:1px solid #ccc;border-radius:4px;transition:border-color .25s}
input[type=text]:focus{border-color:#4CAF50}
.btn{padding:12px 20px;background:#4CAF50;border:none;border-radius:4px;color:#fff;font-size:16px;cursor:pointer}
.btn:hover{background:#45a049}
.message{padding:12px;border-radius:4px;margin-bottom:20px}
.error{background:#fdecea;border:1px solid #f5c6cb;color:#d32f2f}
.success{background:#e7f7e7;border:1px solid #c8e6c9;color:#388e3c}
/* table & search */
.search-form{display:flex;gap:6px;margin:20px 0 10px}
.search-form input{flex:1;padding:8px;border:1px solid #ccc;border-radius:4px}
.search-form button{padding:8px 16px;border:none;border-radius:4px;background:#4CAF50;color:#fff;cursor:pointer}
.search-form button:hover{background:#45a049}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border:1px solid #ccc;text-align:left}
.pagination{text-align:center;margin-top:20px}
.pagination a,.pagination span{margin:0 4px;padding:8px 12px;border:1px solid #ccc;border-radius:4px;text-decoration:none;color:#333}
.pagination .current{background:#4CAF50;color:#fff;border-color:#4CAF50}
</style>
</head><body>
<div class="container">
<h2>ฟอร์มกรอกเพิ่ม/แก้ไขข้อมูลใบอนุญาตขับขี่</h2>
<?php if($error)  echo"<div class='message error'>$error</div>"; ?>
<?php if($success)echo"<div class='message success'>$success</div>"; ?>

<!-- form save/update -->
<form method="POST">
  <div class="form-group">
    <label for="employee_id">รหัสพนักงาน (8 หลัก)</label>
    <input type="text" name="employee_id" id="employee_id" value="<?=htmlspecialchars($employeeId)?>" pattern="\d{8}" maxlength="8" required>
  </div>
  <div class="form-group">
    <label for="work_date">วันหมดอายุ (DD/MM/YYYY พ.ศ.)</label>
    <input type="text" name="work_date" id="work_date" value="<?=htmlspecialchars($dateInput)?>" required readonly>
  </div>
  <button type="submit" class="btn">บันทึก / อัปเดต</button>
</form>

<!-- quick search -->
<form class="search-form" onsubmit="return false;">
  <input type="text" id="quickSearch" placeholder="ค้นหา รหัสพนักงาน" value="<?=htmlspecialchars($search)?>">
  <button type="button" id="btnSearch">ค้นหา</button>
</form>

<table>
<thead><tr><th>รหัสพนักงาน</th><th>วันหมดอายุใบขับขี่</th></tr></thead>
<tbody id="tableBody">
<?php if(!$rows): ?>
  <tr><td colspan="2" style="text-align:center;">ไม่พบข้อมูล</td></tr>
<?php else:
  foreach($rows as $r):
    [$y,$m,$d]=explode('-',$r['license_expiry_date']); ?>
    <tr><td><?=$r['employee_id']?></td><td><?=sprintf('%02d/%02d/%04d',$d,$m,$y)?></td></tr>
<?php endforeach; endif;?>
</tbody>
</table>

<div class="pagination" id="paginator">
<?php if($page>1): ?>
  <a href="?search=<?=urlencode($search)?>&page=<?=$page-1?>">« ก่อนหน้า</a>
<?php endif; for($p=1;$p<=$totalPages;$p++): ?>
  <?php if($p==$page): ?>
    <span class="current"><?=$p?></span>
  <?php else: ?>
    <a href="?search=<?=urlencode($search)?>&page=<?=$p?>"><?=$p?></a>
  <?php endif; endfor;
if($page<$totalPages): ?>
  <a href="?search=<?=urlencode($search)?>&page=<?=$page+1?>">ถัดไป »</a>
<?php endif;?>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/i18n/datepicker-th.min.js"></script>
<script>
  /* -------- datepicker (พ.ศ.) -------- */
  $.datepicker.setDefaults($.datepicker.regional['th']);
  $('#work_date').datepicker({
    dateFormat:'dd/mm/yy',
    changeMonth:true,changeYear:true,isBuddhist:true,
    yearRange:'2400:2680'
  });

  /* -------- Utils -------- */
  function formatDate(ymd) {
    const [y,m,d] = ymd.split('-');
    return d + '/' + m + '/' + y;
  }

  function buildPaginator(page, total, term) {
    let html = '';
    if (page > 1) html += `<a href="#" data-p="${page-1}" data-s="${term}">« ก่อนหน้า</a>`;
    for (let p = 1; p <= total; p++) {
      if (p === page) {
        html += `<span class="current">${p}</span>`;
      } else {
        html += `<a href="#" data-p="${p}" data-s="${term}">${p}</a>`;
      }
    }
    if (page < total) html += `<a href="#" data-p="${page+1}" data-s="${term}">ถัดไป »</a>`;
    return html;
  }

  function render(data, term) {
    const tbody = $('#tableBody');
    if (!data.rows.length) {
      tbody.html('<tr><td colspan="2" style="text-align:center;">ไม่พบข้อมูล</td></tr>');
    } else {
      const rows = data.rows.map(r =>
        `<tr><td>${r.employee_id}</td><td>${formatDate(r.license_expiry_date)}</td></tr>`
      ).join('');
      tbody.html(rows);
    }
    $('#paginator').html(buildPaginator(data.page, data.totalPages, term));
  }

  function ajaxSearch(term, page = 1) {
    fetch(`?ajax=1&search=${encodeURIComponent(term)}&page=${page}`)
      .then(r => r.json())
      .then(d => render(d, term));
  }

  $(function(){
    ajaxSearch($('#quickSearch').val().trim(), <?= $page ?>);

    // event handlers
    $('#quickSearch').on('keyup', function(){
      ajaxSearch(this.value.trim());
    });
    $('#btnSearch').on('click', function(){
      ajaxSearch($('#quickSearch').val().trim());
    });
    $('#paginator').on('click', 'a', function(e){
      e.preventDefault();
      ajaxSearch($(this).data('s'), $(this).data('p'));
    });
  });
</script>
</body></html>
