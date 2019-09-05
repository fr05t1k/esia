<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;


class CliSignerPKCS7 extends AbstractSignerPKCS7 implements SignerInterface
{
    use LoggerAwareTrait;
    
    /**
     * Path to the certificate
     *
     * @var string
     */
    private $certPath;

    /**
     * Path to the private key
     *
     * @var string
     */
    private $privateKeyPath;

    /**
     * Password for the private key
     *
     * @var string
     */
    private $privateKeyPassword;
    
    /**
     * Temporary directory for message signing (must me writable)
     *
     * @var string
     */
    private $tmpPath;

    /**
     * SignerPKCS7 constructor.
     * @param string $certPath
     * @param string $privateKeyPath
     * @param string $privateKeyPassword
     * @param string $tmpPath
     */
    public function __construct(
        string $certPath,
        string $privateKeyPath,
        ?string $privateKeyPassword,
        string $tmpPath
    ) {
        $this->certPath = $certPath;
        $this->privateKeyPath = $privateKeyPath;
        $this->privateKeyPassword = $privateKeyPassword;
        $this->tmpPath = $tmpPath;
        $this->logger = new NullLogger();
    }

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
