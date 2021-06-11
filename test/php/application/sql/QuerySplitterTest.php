<?php

namespace dbmigrate\application\sql;

class QuerySplitterTest extends \PHPUnit_Framework_TestCase
{
    public function splitQueryProvider()
    {
        $originalQuerySplitterTests = [
            'single query' => [
                'full' => "select 1",
                'expectedSplit' => [
                    'select 1',
                ],
            ],
            'single query should remove trailing semicolon' => [
                'full' => "select 1;",
                'expectedSplit' => [
                    'select 1;',
                ],
            ],
            'two queries separated by a blank line' => [
                'full' => "select 1;\n\nselect 2;",
                'expectedSplit' => [
                    'select 1;',
                    'select 2;',
                ],
            ],
            'two queries separate by multiple blank lines & comments' => [
                'full' => "select 1;\n\n-- comment\n/* another comment */\n\nselect 2;",
                'expectedSplit' => [
                    'select 1;',
                    'select 2;'
                ]
            ],
            'one query multiline' => [
                'full' => "select \n 1",
                'expectedSplit' => [
                    "select \n 1",
                ],
            ],
            '-- comments should be removed' => [
                'full' => "select \n -- this comment should be removed\n 1",
                'expectedSplit' => [
                    "select \n  1",
                ],
            ],
            '/* */ comments should be removed' => [
                'full' => "select \n /* this \n comment should be removed\n*/ \n 1",
                'expectedSplit' => [
                    "select \n  \n 1",
                ],
            ],
        ];

        $newQuerySplitterTests = [
            'simple query' => [
                'full' => "SELECT 1;",
                'expectedSplit' => [
                    "SELECT 1"
                ]
            ],
            'multiple queries' => [
                'full' => "SELECT 1;SELECT 2;SELECT 3;      SELECT 4;",
                'expectedSplit' => [
                    "SELECT 1;",
                    "SELECT 2;",
                    "SELECT 3;",
                    "SELECT 4;",
                ]
            ],
            'multiple semicolons' => [
                'full' => "SELECT 1;;;;;;SELECT 2;",
                'expectedSplit' => [
                    "SELECT 1;",
                    "SELECT 2;"
                ]
            ],
            'no semicolons' => [
                'full' => "SELECT 1",
                'expectedSplit' => [
                    "SELECT 1;"
                ]
            ],
            'multiple new lines' => [
                'full' => "SELECT \n\n\n1;\n\n\n\nSELECT \n\n\n\n2;",
                'expectedSplit' => [
                    "SELECT \n\n\n1;",
                    "SELECT \n\n\n\n2;",
                ]
            ],
            'comments inside queries retained if in new line' => [
                'full' => "SELECT\n-- selecting one\n1;\n-- selecting two\nSELECT 2;",
                'expectedSplit' => [
                    "SELECT
                -- selecting one 
                1;",
                    "SELECT 2;",
                ]
            ],
        ];
        return $newQuerySplitterTests;
    }

    /**
     * @dataProvider splitQueryProvider
     */
    public function testSplitQuery($all, $expectedSplit)
    {
        $querySplitter = new QuerySplitter();

        $actualSplit = $querySplitter->split($all);

        $this->assertEquals($expectedSplit, $actualSplit);
    }
}
