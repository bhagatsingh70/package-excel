<?php

namespace Bhagat\Excel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Bhagat\Excel\Concerns\WithChunkReading;
use Bhagat\Excel\Concerns\WithCustomValueBinder;
use Bhagat\Excel\Concerns\WithEvents;
use Bhagat\Excel\Events\ImportFailed;
use Bhagat\Excel\Files\RemoteTemporaryFile;
use Bhagat\Excel\Files\TemporaryFile;
use Bhagat\Excel\Filters\ChunkReadFilter;
use Bhagat\Excel\HasEventBus;
use Bhagat\Excel\Imports\HeadingRowExtractor;
use Bhagat\Excel\Sheet;
use Bhagat\Excel\Transactions\TransactionHandler;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use Throwable;

class ReadChunk implements ShouldQueue
{
    use Queueable, HasEventBus, InteractsWithQueue;

    /**
     * @var int
     */
    public $timeout;

    /**
     * @var int
     */
    public $tries;

    /**
     * @var int
     */
    public $maxExceptions;

    /**
     * @var WithChunkReading
     */
    private $import;

    /**
     * @var IReader
     */
    private $reader;

    /**
     * @var TemporaryFile
     */
    private $temporaryFile;

    /**
     * @var string
     */
    private $sheetName;

    /**
     * @var object
     */
    private $sheetImport;

    /**
     * @var int
     */
    private $startRow;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * @param  WithChunkReading  $import
     * @param  IReader  $reader
     * @param  TemporaryFile  $temporaryFile
     * @param  string  $sheetName
     * @param  object  $sheetImport
     * @param  int  $startRow
     * @param  int  $chunkSize
     */
    public function __construct(WithChunkReading $import, IReader $reader, TemporaryFile $temporaryFile, string $sheetName, $sheetImport, int $startRow, int $chunkSize)
    {
        $this->import        = $import;
        $this->reader        = $reader;
        $this->temporaryFile = $temporaryFile;
        $this->sheetName     = $sheetName;
        $this->sheetImport   = $sheetImport;
        $this->startRow      = $startRow;
        $this->chunkSize     = $chunkSize;
        $this->timeout       = $import->timeout ?? null;
        $this->tries         = $import->tries ?? null;
        $this->maxExceptions = $import->maxExceptions ?? null;
    }

    /**
     * Get the middleware the job should be dispatched through.
     *
     * @return array
     */
    public function middleware()
    {
        return (method_exists($this->import, 'middleware')) ? $this->import->middleware() : [];
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return (method_exists($this->import, 'retryUntil')) ? $this->import->retryUntil() : null;
    }

    /**
     * @param  TransactionHandler  $transaction
     *
     * @throws \Bhagat\Excel\Exceptions\SheetNotFoundException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function handle(TransactionHandler $transaction)
    {
        if (method_exists($this->import, 'setChunkOffset')) {
            $this->import->setChunkOffset($this->startRow);
        }

        if ($this->sheetImport instanceof WithCustomValueBinder) {
            Cell::setValueBinder($this->sheetImport);
        }

        $headingRow = HeadingRowExtractor::headingRow($this->sheetImport);

        $filter = new ChunkReadFilter(
            $headingRow,
            $this->startRow,
            $this->chunkSize,
            $this->sheetName
        );

        $this->reader->setReadFilter($filter);
        $this->reader->setReadDataOnly(config('excel.imports.read_only', true));
        $this->reader->setReadEmptyCells(!config('excel.imports.ignore_empty', false));

        $spreadsheet = $this->reader->load(
            $this->temporaryFile->sync()->getLocalPath()
        );

        $sheet = Sheet::byName(
            $spreadsheet,
            $this->sheetName
        );

        if ($sheet->getHighestRow() < $this->startRow) {
            $sheet->disconnect();

            $this->cleanUpTempFile();

            return;
        }

        $transaction(function () use ($sheet) {
            $sheet->import(
                $this->sheetImport,
                $this->startRow
            );

            $sheet->disconnect();

            $this->cleanUpTempFile();
        });
    }

    /**
     * @param  Throwable  $e
     */
    public function failed(Throwable $e)
    {
        if ($this->temporaryFile instanceof RemoteTemporaryFile) {
            $this->temporaryFile->deleteLocalCopy();
        }

        if ($this->import instanceof WithEvents) {
            $this->registerListeners($this->import->registerEvents());
            $this->raise(new ImportFailed($e));

            if (method_exists($this->import, 'failed')) {
                $this->import->failed($e);
            }
        }
    }

    private function cleanUpTempFile()
    {
        if (!config('excel.temporary_files.force_resync_remote')) {
            return true;
        }

        if (!$this->temporaryFile instanceof RemoteTemporaryFile) {
            return true;
        }

        return $this->temporaryFile->deleteLocalCopy();
    }
}
