import { spawn } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const snapshotDir = path.join(root, 'storage', 'app', 'prod-log-snapshot');

export const CHANNELS = [
  ['job', 'job-{date}.log'],
  ['vk', 'vk/posts/error-{date}.log'],
  ['vk-reposts', 'vk/reposts/error-{date}.log'],
  ['vk-sync', 'vk/sync/error-{date}.log'],
  ['vk-images', 'vk/images/error-{date}.log'],
  ['check_tokens', 'vk/tokens/error-{date}.log'],
  ['stats', 'vk/stats/error-{date}.log'],
  ['llm', 'llm/error-{date}.log'],
];

export function parseArgs(argv) {
  const args = {};
  for (const item of argv) {
    if (!item.startsWith('--')) {
      continue;
    }
    const body = item.slice(2);
    const eq = body.indexOf('=');
    if (eq === -1) {
      args[body] = true;
    } else {
      args[body.slice(0, eq)] = body.slice(eq + 1);
    }
  }

  return args;
}

export function resolveWindow(args, now = new Date()) {
  const hasDate = typeof args.date === 'string';
  const hasMinutes = args.minutes !== undefined && args.minutes !== true;
  const hasHours = args.hours !== undefined && args.hours !== true;
  const hasFrom = typeof args.from === 'string';
  const hasTo = typeof args.to === 'string';
  const modes = [hasDate, hasMinutes, hasHours, hasFrom || hasTo].filter(Boolean);
  if (modes.length > 1) {
    throw new Error('Укажите один интервал: --date, --minutes, --hours или --from/--to.');
  }

  if (hasDate) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(args.date)) {
      throw new Error('date нужен в виде YYYY-MM-DD.');
    }
    const from = new Date(`${args.date}T00:00:00+03:00`);
    if (Number.isNaN(from.getTime())) {
      throw new Error('date нужен в виде YYYY-MM-DD.');
    }

    return { from, to: new Date(from.getTime() + 24 * 60 * 60 * 1000 - 1000) };
  }

  if (hasMinutes) {
    return spanBack(args.minutes, 1, 10080, 'minutes', now);
  }
  if (hasHours) {
    return spanBack(args.hours, 1, 168, 'hours', now);
  }

  const to = hasTo ? parseTime(args.to, 'to') : now;
  const from = hasFrom ? parseTime(args.from, 'from') : new Date(to.getTime() - 24 * 60 * 60 * 1000);
  if (from > to) {
    throw new Error('Начало интервала позже конца.');
  }

  return { from, to };
}

export function moscowDates(from, to) {
  const dates = [];
  let cursor = moscowYmd(from);
  const end = moscowYmd(to);
  while (cursor <= end) {
    dates.push(cursor);
    cursor = nextYmd(cursor);
  }

  return dates;
}

export function remotePath(pattern, date) {
  return pattern.replace('{date}', date);
}

export function countLines(body) {
  let jsonLines = 0;
  let textLines = 0;
  for (const line of body.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (trimmed === '') {
      continue;
    }
    if (trimmed.startsWith('{')) {
      jsonLines += 1;
    } else {
      textLines += 1;
    }
  }

  return { json_lines: jsonLines, text_lines: textLines };
}

export function redact(text, secrets) {
  let safe = text;
  for (const secret of secrets) {
    if (typeof secret === 'string' && secret !== '') {
      safe = safe.split(secret).join('***');
    }
  }

  return safe;
}

export function artisanArgs(directory, window, args) {
  const command = [
    'artisan',
    'logs:query',
    '--no-ansi',
    '--no-interaction',
    `--dir=${directory}`,
    `--from=${window.from.toISOString()}`,
    `--to=${window.to.toISOString()}`,
    '--max-lines=50000',
  ];
  const fields = [
    ['level', '--level'],
    ['event', '--event'],
    ['request', '--request'],
    ['component', '--component'],
    ['channel', '--channel'],
    ['limit', '--limit'],
    ['known', '--known'],
  ];
  for (const [key, flag] of fields) {
    const value = args[key];
    if (typeof value === 'string' && value !== '') {
      command.push(`${flag}=${value}`);
    }
  }

  return command;
}

function spanBack(raw, min, max, name, now) {
  const amount = Number(raw);
  if (!Number.isInteger(amount) || amount < min || amount > max) {
    throw new Error(`${name} должен быть целым числом от ${min} до ${max}.`);
  }
  const unit = name === 'hours' ? 60 * 60 * 1000 : 60 * 1000;

  return { from: new Date(now.getTime() - amount * unit), to: now };
}

function parseTime(value, name) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    throw new Error(`${name} должен быть датой ISO 8601.`);
  }

  return date;
}

function moscowYmd(date) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Moscow',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date);
}

function nextYmd(ymd) {
  const date = new Date(`${ymd}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + 1);

  return date.toISOString().slice(0, 10);
}

function localPath(directory, remote) {
  return path.join(directory, ...remote.split('/'));
}

async function loadFtp() {
  if (process.env.PROD_LOG_FTP_HOST && process.env.PROD_LOG_FTP_USER && process.env.PROD_LOG_FTP_PASSWORD) {
    return {
      host: process.env.PROD_LOG_FTP_HOST,
      user: process.env.PROD_LOG_FTP_USER,
      password: process.env.PROD_LOG_FTP_PASSWORD,
    };
  }

  const mcpPath = path.join(root, '.cursor', 'mcp.json');
  let mcp;
  try {
    mcp = JSON.parse(await readFile(mcpPath, 'utf8'));
  } catch {
    throw new Error('Не заданы PROD_LOG_FTP_HOST, PROD_LOG_FTP_USER и PROD_LOG_FTP_PASSWORD.');
  }
  const env = mcp?.mcpServers?.['prod-logs']?.env ?? {};
  if (!env.PROD_LOG_FTP_HOST || !env.PROD_LOG_FTP_USER || !env.PROD_LOG_FTP_PASSWORD) {
    throw new Error('Не заданы PROD_LOG_FTP_HOST, PROD_LOG_FTP_USER и PROD_LOG_FTP_PASSWORD.');
  }

  return {
    host: env.PROD_LOG_FTP_HOST,
    user: env.PROD_LOG_FTP_USER,
    password: env.PROD_LOG_FTP_PASSWORD,
  };
}

function isMissingFile(code, stderr) {
  if (/425/.test(stderr)) {
    return false;
  }
  if (code === 78) {
    return true;
  }
  if (code === 9 && /denied you to change to the given directory/i.test(stderr)) {
    return true;
  }

  return /no such file|does not exist/i.test(stderr);
}

function curlOnce(ftp, remote) {
  return new Promise((resolve, reject) => {
    const child = spawn('curl', [
      '--config', '-',
      '--noproxy', '*',
      '--ipv4',
      '--disable-epsv',
      '--disable-eprt',
      '--ftp-pasv',
      '--silent',
      '--show-error',
      '--max-time', '90',
      `ftp://${ftp.host}/${remote}`,
    ], { windowsHide: true });
    const out = [];
    const err = [];
    child.stdout.on('data', (chunk) => out.push(chunk));
    child.stderr.on('data', (chunk) => err.push(chunk));
    child.on('error', (error) => reject(error));
    const login = `${ftp.user}:${ftp.password}`.replaceAll('"', '\\"');
    child.stdin.end(`user = "${login}"\n`);
    child.on('close', (code) => {
      const stderr = redact(Buffer.concat(err).toString('utf8'), [ftp.password, ftp.user]).trim();
      if (code !== 0) {
        resolve({
          ok: false,
          missing: isMissingFile(code, stderr),
          error: stderr || `curl ${code}`,
        });
        return;
      }
      resolve({ ok: true, body: Buffer.concat(out).toString('utf8') });
    });
  });
}

function sleep(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

async function curl(ftp, remote) {
  let last;
  for (let attempt = 0; attempt < 6; attempt += 1) {
    last = await curlOnce(ftp, remote);
    if (last.ok || last.missing) {
      return last;
    }
    const pause = /425/.test(last.error ?? '') ? 3000 * (attempt + 1) : 500 * (attempt + 1);
    await sleep(pause);
  }

  return last;
}

async function download(ftp, directory, dates) {
  const files = [];
  const missingFiles = [];
  const downloadErrors = [];

  for (const date of dates) {
    for (const [channel, pattern] of CHANNELS) {
      const remote = remotePath(pattern, date);
      const file = await curl(ftp, remote);
      if (!file.ok) {
        const item = { channel, date, path: remote };
        if (file.missing) {
          missingFiles.push(item);
        } else {
          downloadErrors.push({ ...item, error: file.error });
        }
        continue;
      }
      const destination = localPath(directory, remote);
      await mkdir(path.dirname(destination), { recursive: true });
      await writeFile(destination, file.body);
      files.push({
        channel,
        date,
        path: remote,
        bytes: Buffer.byteLength(file.body),
        ...countLines(file.body),
      });
    }
  }

  return { files, missing_files: missingFiles, download_errors: downloadErrors };
}

function runArtisan(args) {
  return new Promise((resolve, reject) => {
    const child = spawn('php', args, { cwd: root, windowsHide: true });
    const out = [];
    const err = [];
    child.stdout.on('data', (chunk) => out.push(chunk));
    child.stderr.on('data', (chunk) => err.push(chunk));
    child.on('error', (error) => reject(error));
    child.on('close', (code) => {
      const stdout = Buffer.concat(out).toString('utf8').trim();
      const stderr = Buffer.concat(err).toString('utf8').trim();
      const start = stdout.indexOf('{');
      const end = stdout.lastIndexOf('}');
      if (start === -1 || end < start) {
        reject(new Error(stderr || stdout || `php artisan logs:query завершился с кодом ${code}`));
        return;
      }
      const json = stdout.slice(start, end + 1);
      let payload;
      try {
        payload = JSON.parse(json);
      } catch {
        reject(new Error(stderr || 'Ответ сборщика не JSON.'));
        return;
      }
      if (code !== 0) {
        reject(new Error(payload.error || stderr || `php artisan logs:query завершился с кодом ${code}`));
        return;
      }
      resolve(payload);
    });
  });
}

export async function collect(args, directory = snapshotDir) {
  const window = resolveWindow(args);
  const dates = moscowDates(window.from, window.to);
  const ftp = await loadFtp();
  await rm(directory, { recursive: true, force: true });
  await mkdir(directory, { recursive: true });
  const collected = await download(ftp, directory, dates);
  const query = await runArtisan(artisanArgs(directory, window, args));

  return {
    collected: {
      from: window.from.toISOString(),
      to: window.to.toISOString(),
      timezone: 'UTC',
      directory,
      dates,
      ...collected,
    },
    query,
  };
}

async function selfTest() {
  const now = new Date('2026-10-25T12:00:00Z');
  const day = resolveWindow({ date: '2026-10-24' }, now);
  const crossed = moscowDates(new Date('2026-10-24T20:00:00Z'), new Date('2026-10-24T22:00:00Z'));
  const back = resolveWindow({ minutes: 10 }, now);
  const counts = countLines('{"level":"ERROR"}\nplain text\n\n{"level":"WARNING"}\n');
  const hidden = redact('login secret-user failed', ['secret-user']);
  const command = artisanArgs('D:\\snap', day, { channel: 'job', known: 'abcd1234' });
  const datesOk = day.from.toISOString() === '2026-10-23T21:00:00.000Z'
    && day.to.toISOString() === '2026-10-24T20:59:59.000Z'
    && crossed.join(',') === '2026-10-24,2026-10-25'
    && back.from.toISOString() === '2026-10-25T11:50:00.000Z';
  const linesOk = counts.json_lines === 2 && counts.text_lines === 1 && hidden === 'login *** failed';
  const commandOk = command.includes('--dir=D:\\snap')
    && command.includes('--channel=job')
    && command.includes('--known=abcd1234')
    && command.includes('--max-lines=50000');
  let queryOk = false;
  const fixture = path.join(root, 'storage', 'app', 'prod-log-snapshot-self-test');
  await rm(fixture, { recursive: true, force: true });
  await mkdir(fixture, { recursive: true });
  await writeFile(path.join(fixture, 'job-2026-10-24.log'), [
    '{"timestamp":"2026-10-24T10:00:00Z","level":"ERROR","message":"failed 15","event_name":"vk.post","log_channel":"job"}',
    '{"timestamp":"2026-10-24T11:00:00Z","level":"ERROR","message":"failed 16","event_name":"vk.post","log_channel":"job"}',
    '[2026-10-24 12:00:00] production.ERROR: old line',
    '{"timestamp":"2026-10-24T12:00:00Z","level":"INFO","message":"posted","event_name":"vk.post","log_channel":"job"}',
  ].join('\n'));
  const query = await runArtisan(artisanArgs(fixture, day, {}));
  queryOk = query.groups?.length === 1
    && query.groups[0].count === 2
    && query.skipped_lines === 1
    && query.matched === 2;
  await rm(fixture, { recursive: true, force: true });
  if (!datesOk || !linesOk || !commandOk || !queryOk) {
    process.stderr.write('self-test failed\n');
    process.exit(1);
  }
  process.stdout.write('ok\n');
}

const isDirect = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (isDirect && process.argv.includes('--self-test')) {
  selfTest().catch((error) => {
    process.stderr.write(`${error instanceof Error ? error.message : String(error)}\n`);
    process.exit(1);
  });
} else if (isDirect) {
  collect(parseArgs(process.argv.slice(2))).then((payload) => {
    process.stdout.write(`${JSON.stringify(payload)}\n`);
  }).catch((error) => {
    const message = redact(
      error instanceof Error ? error.message : String(error),
      [process.env.PROD_LOG_FTP_PASSWORD, process.env.PROD_LOG_FTP_USER],
    );
    process.stdout.write(`${JSON.stringify({ error: message })}\n`);
    process.exit(1);
  });
}
