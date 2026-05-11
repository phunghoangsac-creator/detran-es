const fs = require('fs');

const file = 'pix_mode.txt';

let estado = 'ativo';
let tempoRestante = 60; // segundos (1 minuto)

function escreverArquivo(texto) {
  fs.writeFileSync(file, texto);
}

function atualizarConsole() {
  const total = estado === 'ativo' ? 60 : 180;
  const progresso = ((total - tempoRestante) / total) * 100;

  process.stdout.clearLine(0);
  process.stdout.cursorTo(0);
  process.stdout.write(
    `Estado: ${estado.toUpperCase()} | Tempo restante: ${tempoRestante}s | ${progresso.toFixed(0)}%`
  );
}

function loop() {
  escreverArquivo(estado);
  atualizarConsole();

  const interval = setInterval(() => {
    tempoRestante--;
    atualizarConsole();

    if (tempoRestante <= 0) {
      clearInterval(interval);

      // alterna estado
      if (estado === 'ativo') {
        estado = 'desativo';
        tempoRestante = 180; // 3 minutos
      } else {
        estado = 'ativo';
        tempoRestante = 60; // 1 minuto
      }

      loop(); // reinicia ciclo
    }
  }, 1000);
}

// inicia
loop();