<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\GenericEvent;


class ConfigService {

	public const TESSERACT_ENABLED = 'tesseract_enabled';
	public const TESSERACT_PSM = 'tesseract_psm';
	public const TESSERACT_LANG = 'tesseract_lang';
	public const TESSERACT_PDF = 'tesseract_pdf';
	public const TESSERACT_PDF_LIMIT = 'tesseract_pdf_limit';
	public const TESSERACT_CPU_BUDGET = 'tesseract_cpu_budget';
	public const TESSERACT_PARALLEL_JOBS = 'tesseract_parallel_jobs';
	public const TESSERACT_THREADS = 'tesseract_threads';

	private const MAX_CPU_BUDGET = 256;
	private const DEFAULT_PSM = 4;
	private const MAX_PARALLEL_JOBS = 256;
	private const MAX_PDF_PAGES = 100000;
	private const MAX_THREADS = 256;

	/** @var array<string, string> */
	private array $defaults;


	public function __construct(private IAppConfig $appConfig) {
		$this->defaults = [
			self::TESSERACT_ENABLED => '1',
			self::TESSERACT_PSM => (string)self::DEFAULT_PSM,
			self::TESSERACT_LANG => 'eng',
			self::TESSERACT_PDF => '0',
			self::TESSERACT_PDF_LIMIT => '0',
			self::TESSERACT_CPU_BUDGET => (string)$this->getDefaultCpuBudget(),
			self::TESSERACT_PARALLEL_JOBS => (string)$this->getDefaultParallelJobs(),
			self::TESSERACT_THREADS => '1',
		];
	}


	public function onGetConfig(GenericEvent $event): void {
		$config = $event->getArgument('config');
		if (!is_array($config)) {
			return;
		}

		$config['files_fulltextsearch_tesseract'] = [
			'version' => $this->getAppValue('installed_version'),
			'enabled' => $this->getAppValue(self::TESSERACT_ENABLED),
			'psm' => $this->getAppValue(self::TESSERACT_PSM),
			'lang' => $this->getAppValue(self::TESSERACT_LANG),
			'pdf' => $this->getAppValue(self::TESSERACT_PDF),
			'pdf_limit' => $this->getAppValue(self::TESSERACT_PDF_LIMIT),
			'cpu_budget' => $this->getAppValue(self::TESSERACT_CPU_BUDGET),
			'parallel_jobs' => $this->getAppValue(self::TESSERACT_PARALLEL_JOBS),
			'threads' => $this->getAppValue(self::TESSERACT_THREADS),
		];
		$event->setArgument('config', $config);
	}


	/**
	 * @return array<string, string>
	 */
	public function getConfig(): array {
		$data = [];
		foreach (array_keys($this->defaults) as $key) {
			$data[$key] = $this->getAppValue($key);
		}

		return $data;
	}


	public function getDefaultValue(string $key): string {
		return $this->defaults[$key] ?? '';
	}


	/**
	 * @param array<string, mixed> $values
	 */
	public function setConfig(array $values): void {
		foreach (array_keys($this->defaults) as $key) {
			if (!array_key_exists($key, $values)) {
				continue;
			}

			$this->setAppValue($key, $this->normalizeValue($key, $values[$key]));
		}
	}


	public function isEnabled(): bool {
		return $this->optionIsSelected(self::TESSERACT_ENABLED);
	}


	public function isPdfEnabled(): bool {
		return $this->optionIsSelected(self::TESSERACT_PDF);
	}


	public function getPageSegmentationMode(): int {
		return $this->normalizeInteger(
			$this->getAppValue(self::TESSERACT_PSM),
			0,
			13,
			self::DEFAULT_PSM
		);
	}


	/**
	 * @return list<string>
	 */
	public function getLanguages(): array {
		return explode(',', $this->normalizeLanguages($this->getAppValue(self::TESSERACT_LANG)));
	}


	public function getPdfPageLimit(): int {
		return $this->normalizeInteger(
			$this->getAppValue(self::TESSERACT_PDF_LIMIT),
			0,
			self::MAX_PDF_PAGES,
			0
		);
	}


	public function getParallelJobs(): int {
		return $this->normalizeInteger(
			$this->getAppValue(self::TESSERACT_PARALLEL_JOBS),
			1,
			self::MAX_PARALLEL_JOBS,
			$this->getDefaultParallelJobs()
		);
	}


	public function getCpuBudget(): int {
		return $this->normalizeInteger(
			$this->getAppValue(self::TESSERACT_CPU_BUDGET),
			1,
			self::MAX_CPU_BUDGET,
			$this->getDefaultCpuBudget()
		);
	}


	public function getThreadLimit(): int {
		return $this->normalizeInteger(
			$this->getAppValue(self::TESSERACT_THREADS),
			1,
			self::MAX_THREADS,
			1
		);
	}


	/**
	 * The effective limits always fit inside the shared OCR CPU budget.
	 */
	public function getEffectiveThreadLimit(): int {
		return min($this->getThreadLimit(), $this->getCpuBudget());
	}


	public function getEffectiveParallelJobs(): int {
		$threadsPerJob = $this->getEffectiveThreadLimit();
		$jobsWithinBudget = max(1, intdiv($this->getCpuBudget(), $threadsPerJob));

		return min($this->getParallelJobs(), $jobsWithinBudget);
	}


	public function getAppValue(string $key): string {
		$value = $this->appConfig->getAppValueString($key, $this->defaults[$key] ?? '');

		return array_key_exists($key, $this->defaults) ? $this->normalizeValue($key, $value) : $value;
	}


	public function setAppValue(string $key, string $value): void {
		$this->appConfig->setAppValueString($key, $value);
	}


	public function deleteAppValue(string $key): void {
		$this->appConfig->deleteAppValue($key);
	}


	public function optionIsSelected(string $key): bool {
		return $this->getAppValue($key) === '1';
	}


	private function normalizeValue(string $key, mixed $value): string {
		return match ($key) {
			self::TESSERACT_ENABLED,
			self::TESSERACT_PDF => $this->normalizeBoolean($value),
			self::TESSERACT_PSM => (string)$this->normalizeInteger(
				$value,
				0,
				13,
				self::DEFAULT_PSM
			),
			self::TESSERACT_LANG => $this->normalizeLanguages($value),
			self::TESSERACT_PDF_LIMIT => (string)$this->normalizeInteger(
				$value,
				0,
				self::MAX_PDF_PAGES,
				0
			),
			self::TESSERACT_CPU_BUDGET => (string)$this->normalizeInteger(
				$value,
				1,
				self::MAX_CPU_BUDGET,
				$this->getDefaultCpuBudget()
			),
			self::TESSERACT_PARALLEL_JOBS => (string)$this->normalizeInteger(
				$value,
				1,
				self::MAX_PARALLEL_JOBS,
				$this->getDefaultParallelJobs()
			),
			self::TESSERACT_THREADS => (string)$this->normalizeInteger(
				$value,
				1,
				self::MAX_THREADS,
				1
			),
			default => $this->defaults[$key] ?? '',
		};
	}


	private function normalizeBoolean(mixed $value): string {
		return in_array($value, [1, '1', true, 'true', 'on'], true) ? '1' : '0';
	}


	private function normalizeInteger(
		mixed $value,
		int $minimum,
		int $maximum,
		int $default
	): int {
		if (is_int($value)) {
			$integer = $value;
		} elseif (is_string($value) && preg_match('/^-?\d+$/D', trim($value)) === 1) {
			$integer = (int)trim($value);
		} else {
			$integer = $default;
		}

		return max($minimum, min($maximum, $integer));
	}


	private function normalizeLanguages(mixed $value): string {
		if (!is_string($value)) {
			return $this->defaults[self::TESSERACT_LANG];
		}

		$languages = [];
		foreach (preg_split('/[,+]/', $value) ?: [] as $language) {
			$language = trim($language);
			if ($language === ''
				|| strlen($language) > 64
				|| preg_match('/^[A-Za-z0-9_.\/-]+$/D', $language) !== 1) {
				continue;
			}

			$languages[$language] = true;
			if (count($languages) === 32) {
				break;
			}
		}

		return $languages === []
			? $this->defaults[self::TESSERACT_LANG]
			: implode(',', array_keys($languages));
	}


	private function getDefaultParallelJobs(): int {
		return min(self::MAX_PARALLEL_JOBS, $this->getDefaultCpuBudget());
	}


	private function getDefaultCpuBudget(): int {
		return max(1, min(self::MAX_CPU_BUDGET, intdiv($this->getCpuCount(), 2)));
	}


	private function getCpuCount(): int {
		$cpuCount = $this->getLinuxCpuCount();
		$cpuQuota = $this->getLinuxCpuQuota();
		if ($cpuCount > 0 && $cpuQuota > 0) {
			return min($cpuCount, $cpuQuota);
		}
		if ($cpuCount > 0) {
			return $cpuCount;
		}
		if ($cpuQuota > 0) {
			return $cpuQuota;
		}

		$windowsCpuCount = (int)getenv('NUMBER_OF_PROCESSORS');
		if ($windowsCpuCount > 0) {
			return $windowsCpuCount;
		}

		if (function_exists('shell_exec')) {
			$cpuCount = (int)@shell_exec('getconf _NPROCESSORS_ONLN 2>/dev/null');
			if ($cpuCount > 0) {
				return $cpuCount;
			}

			$cpuCount = (int)@shell_exec('sysctl -n hw.ncpu 2>/dev/null');
			if ($cpuCount > 0) {
				return $cpuCount;
			}
		}

		return 2;
	}


	private function getLinuxCpuCount(): int {
		$status = @file_get_contents('/proc/self/status');
		if ($status === false
			|| preg_match('/^Cpus_allowed_list:\s*(.+)$/m', $status, $matches) !== 1) {
			return 0;
		}

		$cpuCount = 0;
		foreach (explode(',', trim($matches[1])) as $range) {
			$bounds = array_map('intval', explode('-', $range, 2));
			$cpuCount += count($bounds) === 2 ? max(0, $bounds[1] - $bounds[0] + 1) : 1;
		}

		return $cpuCount;
	}


	private function getLinuxCpuQuota(): int {
		$cpuMax = @file_get_contents('/sys/fs/cgroup/cpu.max');
		if (is_string($cpuMax)
			&& preg_match('/^(\d+)\s+(\d+)/', trim($cpuMax), $matches) === 1) {
			return $this->calculateCpuQuota((int)$matches[1], (int)$matches[2]);
		}

		$quota = @file_get_contents('/sys/fs/cgroup/cpu/cpu.cfs_quota_us');
		$period = @file_get_contents('/sys/fs/cgroup/cpu/cpu.cfs_period_us');
		if (is_string($quota) && is_string($period)) {
			return $this->calculateCpuQuota((int)$quota, (int)$period);
		}

		return 0;
	}


	private function calculateCpuQuota(int $quota, int $period): int {
		if ($quota <= 0 || $period <= 0) {
			return 0;
		}

		return max(1, (int)ceil($quota / $period));
	}
}
