<?php

namespace Issues;

class Issue1051Test extends \PHPUnit_Framework_TestCase
{

	public function testNoticeOnSetColumns()
	{
		$mpdf = new \Mpdf\Mpdf();
		$mpdf->SetColumns(2);

		$mpdf->WriteHTML('Some text...');
		$mpdf->AddColumn();
		$mpdf->WriteHTML('Next column...');

		$output = $mpdf->output('', 'S');
		$this->assertStringStartsWith('%PDF-', $output);
	}

}
