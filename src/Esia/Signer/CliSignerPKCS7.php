<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;


class CliSignerPKCS7 extends SignerPKCS7
{
    use LoggerAwareTrait;

    /**
     * @param string $message
     * @return string
     * @throws SignFailException
     */

    public function sign(string $message): string {
        $this->checkFilesExists();

        // random unique directories for sign
        $messageFile = $this->tmpPath . DIRECTORY_SEPARATOR . $this->getRandomString();
        $signFile = $this->tmpPath . DIRECTORY_SEPARATOR . $this->getRandomString();
        file_put_contents($messageFile, $message);

        $this->run(
            'openssl '.
            'smime -sign -binary -outform DER -noattr '.
            '-signer '.escapeshellarg($this->certPath).' '.
            '-inkey '.escapeshellarg($this->privateKeyPath). ' '.
            '-passin '.escapeshellarg('pass:'.$this->privateKeyPassword).' '.
            '-in '.escapeshellarg($messageFile).' '.
            '-out '.escapeshellarg($signFile)
        );

        $signed = file_get_contents($signFile);
        $sign = $this->urlSafe(base64_encode($signed));

        unlink($signFile);
        unlink($messageFile);
        return $sign;
    }

    private function run($command) {
        $process = proc_open($command, [
            ['pipe', 'w'], // stdout
            ['pipe', 'w'], // stderr
        ], $pipes);
        $result = stream_get_contents($pipes[0]);
        fclose($pipes[0]);
        $errors = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $code = proc_close($process);
        if (0 != $code) {
            $errors = trim($errors) ?: 'unknown';
            $this->logger->error('Sign fail');
            $this->logger->error('SSL error: ' . $errors);
            throw new SignFailException($errors);
        }
        return $result;
    }
}
