<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 LinguaLeo
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace LinguaLeo\MySQL;

use LinguaLeo\DataQuery\Criteria;

class RoutingTest extends \PHPUnit_Framework_TestCase
{
    private static $tablesMap = [
        'translate' => [
            'db' => 'lang',
            'options' => ['localized']
        ],
        'word_user' => [
            'db' => 'leotestdb_i18n',
            'options' => ['chunked', 'spotted'],
        ],
        'server_node' => [
            'options' => 'chunked'
        ],
        'word' => [
            'db' => 'test',
            'options' => ['spotted', 'localized']
        ],
        'content' => [
            'options' => ['spotted', 'chunked']
        ],
        'word_set' => [
            'table_name' => 'glossary'
        ]
    ];

    public function provideRouteArguments()
    {
        return [
            ['user', ['spot_id' => 3, 'chunk_id' => 99, 'locale' => 'ru'], 'linguadb.user'],
            ['translate', ['locale' => 'ru'], 'lang_ru.translate'],
            ['word_user', ['spot_id' => 3, 'chunk_id' => 99, 'locale' => 'ru'], 'leotestdb_i18n_3.word_user_99'],
            ['server_node', ['spot_id' => 3, 'chunk_id' => 99, 'locale' => 'pt'], 'linguadb.server_node_99'],
            ['word', ['spot_id' => 3, 'chunk_id' => 99, 'locale' => 'pt'], 'test_3_pt.word'],
            ['content', ['spot_id' => 'c4ca42', 'chunk_id' => 99, 'locale' => 'ru'], 'linguadb_c4ca42.content_99'],
            ['word_set', ['locale' => 'ru'], 'linguadb.glossary']
        ];
    }

    /**
     * @dataProvider provideRouteArguments
     */
    public function testRouteGetter($tableName, $arguments, $expected)
    {
        $routing = new Routing('linguadb', self::$tablesMap);
        $this->assertSame($expected, (string) $routing->getRoute(new Criteria($tableName, $arguments)));
    }

    /**
     * @expectedException \LinguaLeo\MySQL\Exception\RoutingException
     * @expectedExceptionMessage Unknown "qaz" option type
     */
    public function testUnknownOptionType()
    {
        $routing = new Routing('ololo', ['atata' => ['options' => 'qaz']]);
        $routing->getRoute(new Criteria('atata'));
    }
}
