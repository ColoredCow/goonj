<?php
use Civi\Inlay\ArraySchema;

// This class can be run without any Civi db.
require_once( __DIR__ . '/../../../../Civi/Inlay/ArraySchema.php');

class ArraySchemaTest extends \PHPUnit\Framework\TestCase /*implements HeadlessInterface, HookInterface, TransactionalInterface*/ {

  public function testOwnSchema() {
    // This will test and throw exception if it fails.
    new ArraySchema(ArraySchema::getOwnSchema());
    $this->assertTrue(TRUE);
  }

  /**
   */
  public function testExample() {
    $this->runTests('files',
      new ArraySchema([
        '/^\d+$/' => ['MAY', 'array', 'schema' => [
          'name' => ['MUST', 'string'],
          'children' => ['MAY', 'array', 'recurse' => []]
        ]]
      ]),
      [
        [
          [
            ['name' => 'README.md'],
            ['name' => 'css', 'children' => [
              ['name' => 'web.css'],
              ['name' => 'print.css'],
            ]],
          ],
          0
        ]
      ]);
  }

  protected function runTests(string $setDescr, ArraySchema $a, array $casesAndErrorCounts) {
    // print "\n## $setDescr ##\n\n";
    foreach ($casesAndErrorCounts as $i => $c) {
      if (count($c) < 3) {
        $c[] = NULL;
      }
      [$input, $errorCount, $result] = $c;
      if ($errorCount && $result) {
        $this->fail("Invalid test: do not pass a result value with an expected error. $setDescr #$i");
      }
      $mutableInput = $input;
      $errors = $a->getErrors($mutableInput);
      $this->assertCount($errorCount, $errors, "$setDescr #$i Failed with input " . json_encode($input, JSON_UNESCAPED_SLASHES) . "\n" . json_encode($errors));
      if (!$errors && $result !== NULL) {
        $this->assertSame($result, $mutableInput, "$setDescr #$i Failed with input " . json_encode($input, JSON_UNESCAPED_SLASHES) . "\n" . json_encode($errors));
      }
    }
  }

  public function testSimples() {

    $this->runTests('simples',
      new ArraySchema([
        'x' => ['MUST', 'integer'],
        'z' => ['MAY', 'string|NULL'],
        'e' => ['MAY', 'string', 'oneOf' => ['aye', 'bee']],
        'b' => ['MAY', 'integer', 'gt' => 2, 'lte' => '5'],
        'n2' => ['MAY', 'numeric'],
      ]),
      [
        [ ['x' => 123],0 ],
        [ ['x' => 'hello'], 1 ],
        [ ['y' => 123], 2 ],
        [ ['x' => []], 1 ],
        [ ['x' => 123, 'z' => 123], 1 ],
        [ ['x' => 123, 'z' => 'zed'], 0 ],
        [ ['x' => 123, 'z' => NULL], 0 ],
        [ ['x' => 123, 'e' => 'cee'], 1 ],
        [ ['x' => 123, 'e' => 'bee'], 0 ],
        [ ['x' => 123, 'b' => 1], 1 ],
        [ ['x' => 123, 'b' => 3], 0 ],
        [ ['x' => 123, 'b' => 5], 0 ],
        [ ['x' => 123, 'b' => 6], 1 ],
        [ ['x' => 123, 'n2' => 123], 0],
        [ ['x' => 123, 'n2' => '123'], 0],
        [ ['x' => 123, 'n2' => '123.23'], 0],
        [ ['x' => 123, 'n2' => 'not a number'], 1],
      ]);
  }

  public function testNested() {

    $this->runTests('nested',
      new ArraySchema([
        'trunk' => ['MUST', 'array', 'schema' => [
          'branch' => [
            'MAY', 'array', 'schema' => [
              'twig' => ['MUST', 'integer'],
              '//' => ['MAY', NULL] // allow any keys in here.
            ],
          ]
        ]]
      ]),
      [
        [ ['x' => 123], 2 ],
        [ ['trunk' => 123], 1 ], // 123 not array
        [ ['trunk' => ['x' => 123]], 1 ], // x unexpected
        [ ['trunk' => ['branch' => 123]], 1 ], // 123 not array
        [ ['trunk' => ['branch' => ['twig' => 123, 'whatever' => 'else']]], 0 ],
        [ ['trunk' => ['branch' => ['twig' => 'word']]], 1 ], // 'word' not integer
      ]
    );
  }

  public function testCoerce() {
    $wanted = ['i' => '123' ];
    $this->runTests('coerce to string',
      (new ArraySchema(['i' => ['MUST', 'string']]))->setCoerce(),
      [
        [ $wanted, 0 ],
        [ ['i' => 123 ], 0, $wanted ],
        [ ['i' => (float) 123.0 ], 0, $wanted ],
        [ ['i' => (float) 123.1 ], 0, ['i' => '123.1'] ],
        [ ['i' => '123.0' ], 0, ['i' => '123.0'] ], // left alone
        [ ['i' => false ], 0, ['i' => ''] ],
        [ ['i' => true ], 0, ['i' => '1'] ],
        [ ['i' => NULL ], 0, ['i' => ''] ],
        [ ['i' => [] ], 1],
      ],
    );

    $wanted = ['i' => 123 ];
    $this->runTests('coerce to int',
      (new ArraySchema(['i' => ['MUST', 'integer']]))->setCoerce(),
      [
        [ $wanted, 0 ],
        [ ['i' => '123' ], 0, $wanted ],
        [ ['i' => (float) 123.0 ], 0, $wanted ],
        [ ['i' => (float) 123.1 ], 0, $wanted ],
        [ ['i' => '123.0' ], 1 ], // If we want an int, we'll put up with it in a string, but we're not having decimals.
        [ ['i' => [234] ], 1], // can't cast arrays.
        [ ['i' => NULL ], 0, ['i' => 0]], // null gets cast to zero
      ],
    );

    $wanted = ['i' => 123.1 ];
    $this->runTests('coerce to double',
      (new ArraySchema(['i' => ['MUST', 'double']]))->setCoerce(),
      [
        [ $wanted, 0, $wanted ],
        [ ['i' => '123.1' ], 0, $wanted ],
        [ ['i' => false ], 1],
      ],
    );

    $this->runTests('coerce to boolean',
      (new ArraySchema(['i' => ['MUST', 'boolean']]))->setCoerce(),
      [
        [ ['i' => false], 0, ['i' => false]],
        [ ['i' => null], 0, ['i' => false]],
        [ ['i' => ''], 0, ['i' => false]],
        [ ['i' => 0], 0, ['i' => false]],
        [ ['i' => true], 0, ['i' => true]],
        [ ['i' => 1], 0, ['i' => true]],
        [ ['i' => 100.2], 0, ['i' => true]],
        [ ['i' => 'hello'], 0, ['i' => true]],
        [ ['i' => '1'], 0, ['i' => true]],
        [ ['i' => ['1']], 1],
      ],
    );

    $this->runTests('trial and error coerceions',
      (new ArraySchema(['i' => ['MUST', 'boolean|integer|string']]))->setCoerce(),
      [
        [ ['i' => false], 0, ['i' => false]],
        [ ['i' => '123'], 0, ['i' => '123']], // '123' should not be coerced since strings are valid
        [ ['i' => 1.23], 0, ['i' => true]], // 1.23 is not valid, but can be cast to boolean
      ],
    );

    $this->runTests('coerce to with NULL first',
      (new ArraySchema(['i' => ['MUST', 'NULL|integer']]))->setCoerce(),
      [
        [ ['i' => false], 0, ['i' => NULL]], // bools are not null/int, so cast to NULL
        [ ['i' => 'hello'], 0, ['i' => NULL]], // strings are not null/int, so cast to NULL
      ],
    );

    $as = (new ArraySchema([
        'i' => ['MUST', 'integer'],
        'trunk' => ['MAY', 'array', 'schema' => [
            'branch' => ['MUST', 'boolean'],
          ]
        ],
        'foo' => ['MAY', 'string']
      ]))->setFallbacks(['i' => 567, 'trunk' => ['branch' => true]]);
    $this->runTests('coerce to fallbacks', $as,
      [
        // #1 fred is not an int, so fallback should be used. trunk not required.
        [['i' => 'fred'], 0, ['i' => 567]],
        // #2 'i' is fine. trunk is given but the branch value is invalid (not a bool)
        [['i' => 1, 'trunk' => ['branch' => 123]], 0, ['i' => 1, 'trunk' => ['branch' => true]]],
        // #3 'i' is fine, foo is not and we have no fallback: error.
        [['i' => 1, 'foo' => 1], 1],
        // #4 'i' is fine. trunk is given but has no proper branch. The whole trunk should be fallback-ed
        [['i' => 1, 'trunk' => ['twig' => 1]], 0, ['i' => 1, 'trunk' => ['branch' => true]]],
      ],
    );

    $as = (new ArraySchema([ 'i' => ['MUST', 'integer'], ]))
      ->setFallbacks(['i' => 'invalid fallback!']);
    $this->runTests('dodgy fallback', $as, [[['i' => 'fred'], 1]]);

  }
  public function testMissingKeyReplace() {
    $as = (new ArraySchema([ 'i' => ['MUST', 'integer'], ]))
      ->setFallbacks(['i' => 123]);
    $this->runTests('missing key', $as, [[[], 0, ['i' => 123]]]);
  }
}
