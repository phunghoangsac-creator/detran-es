<?php header('Content-Type: text/html; charset=UTF-8'); ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Veículos Consultados</title>
  <link rel="stylesheet" href="https://www.meudetran.ms.gov.br/assets/index-d039bf7f.css">
  <style>
    body{background:#F9F9F9; font-family:Arial,sans-serif}
    .card{border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.08); background:#fff; border:1px solid #e2e8f0; max-width:1280px; margin:0 auto}
    .card-header{background:linear-gradient(90deg,#003F7E,#004F9F);color:#fff;border-top-left-radius:12px;border-top-right-radius:12px; padding:16px; display:flex; justify-content:space-between; align-items:center}
    .card-header h2{color:#fff; margin:0; font-size:20px; font-weight:bold}
    .btn{background:#fff;color:#004F9F;border:1px solid #004F9F;transition:all .2s ease; padding:8px 16px; border-radius:99px; cursor:pointer; text-decoration:none; display:inline-block; font-size:14px}
    .btn:hover{background:#004F9F;color:#fff}
    .table-colored{width:100%; border-collapse:collapse}
    .table-colored th{background:#003F7E;color:#fff; text-align:left; padding:8px}
    .table-colored td{border-bottom:1px solid #eee; padding:8px}
    .table-colored tr:nth-child(even){background:#f7fafc}
    .table-colored tr:hover{background:#E8F0FE}
    @keyframes blink { 50% { opacity: 0.5; } }
  </style>
</head>
<body class="p-4">
  <div class="card">
    <div class="card-header">
      <h2>Histórico de Veículos Consultados</h2>
      <div>
         <button id="clearBtn" class="btn">Limpar Histórico</button>
      </div>
    </div>
    <div class="p-4">
      <table class="table-colored">
        <thead>
          <tr>
            <th>Data/Hora</th>
            <th>Placa</th>
            <th>Renavam</th>
            <th>Resultado</th>
          </tr>
        </thead>
        <tbody id="logsTableBody">
          <tr><td colspan="3" style="text-align:center; padding:20px">Carregando...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    (function(){
      var body = document.getElementById('logsTableBody');
      var clearBtn = document.getElementById('clearBtn');

      function loadLogs(){
        fetch('storage.php?file=consultados_log.json&ts=' + Date.now())
          .then(function(r){ 
             if(r.status === 404) return [];
             return r.json(); 
          })
          .then(function(arr){
            if(!Array.isArray(arr) || arr.length === 0){
               body.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px">Nenhuma consulta registrada ainda.</td></tr>';
               return;
            }
            var html = '';
            for(var i=0; i<arr.length; i++){
               var e = arr[i];
               var ts = e.ts ? new Date(e.ts).toLocaleString('pt-BR') : '-';
               var res = e.resultado || '-';
               
               var statusStyle = '';
               if (res === 'Puxando débitos...') {
                   statusStyle = 'color: orange; font-weight: bold; animation: blink 1s infinite;';
               } else {
                   statusStyle = 'color: green; font-weight: bold;';
               }

               html += '<tr>';
               html += '<td>' + ts + '</td>';
               html += '<td>' + (e.placa || '') + '</td>';
               html += '<td>' + (e.renavam || '') + '</td>';
               html += '<td style="' + statusStyle + '">' + res + '</td>';
               html += '</tr>';
            }
            body.innerHTML = html;
          })
          .catch(function(err){
             console.error(err);
             body.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:red">Erro ao carregar dados.</td></tr>';
          });
      }

      if(clearBtn){
        clearBtn.addEventListener('click', function(){
           if(confirm('Deseja realmente apagar todo o histórico de consultas?')){
              fetch('consultados_clear.php', { method: 'POST' })
                .then(function(){ loadLogs(); })
                .catch(function(){ alert('Erro ao limpar.'); });
           }
        });
      }

      loadLogs();
      // Auto refresh every 2s for faster updates
      setInterval(loadLogs, 2000);
    })();
  </script>
</body>
</html>
