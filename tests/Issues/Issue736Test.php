<?php

namespace Issues;

class Issue736Test extends \PHPUnit\Framework\TestCase
{

	public function testNoNoticeWithUnicodeCharacterAndFontSubDisabled()
	{
		$mpdf = new \Mpdf\Mpdf();
		$mpdf->WriteHTML('&#66352;');

		$output = $mpdf->output('', 'S');
		$this->assertStringStartsWith('%PDF-', $output);
	}

}
