<?php
/**
 * This file is part of the GitElephant package.
 *
 * (c) Matteo Giachino <matteog@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package GitElephant\Objects\Diff
 *
 * Just for fun...
 */

namespace GitElephant\Objects\Diff;

use GitElephant\Objects\Diff\DiffObject,
    GitElephant\Utilities,
    GitElephant\Repository,
    GitElephant\Command\DiffTreeCommand,
    GitElephant\Command\DiffCommand;

/**
 * Represent a collection of diffs between two trees
 *
 * @author Matteo Giachino <matteog@gmail.com>
 */

class Diff implements \ArrayAccess, \Countable, \Iterator
{
    /**
     * @var \GitElephant\Repository
     */
    private $repository;

    /**
     * the cursor position
     *
     * @var int
     */
    private $position;

    /**
     * DiffObject instances
     *
     * @var array
     */
    private $diffObjects;

    /**
     * static generator to generate a Diff object
     *
     * @param \GitElephant\Repository                 $repository repository
     * @param null|string|\GitElephant\Objects\Commit $commit1    first commit
     * @param null|string|\GitElephant\Objects\Commit $commit2    second commit
     * @param null|string                             $path       path to consider
     *
     * @return Diff
     */
    public static function create(Repository $repository, $commit1 = null, $commit2 = null, $path = null)
    {
        $commit = new self($repository);
        $commit->createFromCommand($commit1, $commit2, $path);

        return $commit;
    }

    /**
     * Class constructor
     * bare Diff object
     *
     * @param \GitElephant\Repository $repository  repository instance
     * @param null                    $diffObjects diff objects
     */
    public function __construct(Repository $repository, $diffObjects = null)
    {
        $this->position = 0;
        $this->repository = $repository;
        $this->diffObjects = $diffObjects;
    }

    /**
     * get the commit properties from command
     *
     * @param null $commit1 commit 1
     * @param null $commit2 commit 2
     * @param null $path    path
     *
     * @see ShowCommand::commitInfo
     */
    private function createFromCommand($commit1 = null, $commit2 = null, $path = null)
    {
        if (null === $commit1) {
            $commit1 = $this->getRepository()->getCommit();
        }
        if (is_string($commit1)) {
            $commit1 = $this->getRepository()->getCommit($commit1);
        }
        if ($commit2 === null) {
            if ($commit1->isRoot()) {
                $command = DiffTreeCommand::getInstance()->rootDiff($commit1);
            } else {
                $command = DiffCommand::getInstance()->diff($commit1);
            }
        } else {
            if (is_string($commit2)) {
                $commit2 = $this->getRepository()->getCommit($commit2);
            }
            $command = DiffCommand::getInstance()->diff($commit1, $commit2, $path);
        }
        $outputLines = $this->getCaller()->execute($command)->getOutputLines();
        $this->parseOutputLines($outputLines);
    }

    /**
     * parse the output of a git command showing a commit
     *
     * @param array $outputLines output lines
     */
    private function parseOutputLines($outputLines)
    {
        $this->diffObjects = array();
        $splitArray = Utilities::pregSplitArray($outputLines, '/^diff --git SRC\/(.*) DST\/(.*)$/');
        foreach ($splitArray as $diffObjectLines) {
            $this->diffObjects[] = new DiffObject($diffObjectLines);
        }
    }

    /**
     * @return \GitElephant\Command\Caller
     */
    private function getCaller()
    {
        return $this->getRepository()->getCaller();
    }

    /**
     * Repository setter
     *
     * @param \GitElephant\Repository $repository the repository variable
     */
    public function setRepository($repository)
    {
        $this->repository = $repository;
    }

    /**
     * Repository getter
     *
     * @return \GitElephant\Repository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * ArrayAccess interface
     *
     * @param int $offset offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->diffObjects[$offset]);
    }

    /**
     * ArrayAccess interface
     *
     * @param int $offset offset
     *
     * @return null|mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->diffObjects[$offset]) ? $this->diffObjects[$offset] : null;
    }

    /**
     * ArrayAccess interface
     *
     * @param int   $offset offset
     * @param mixed $value  value
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->diffObjects[] = $value;
        } else {
            $this->diffObjects[$offset] = $value;
        }
    }

    /**
     * ArrayAccess interface
     *
     * @param int $offset offset
     */
    public function offsetUnset($offset)
    {
        unset($this->diffObjects[$offset]);
    }

    /**
     * Countable interface
     *
     * @return int|void
     */
    public function count()
    {
        return count($this->diffObjects);
    }

    /**
     * Iterator interface
     *
     * @return mixed
     */
    public function current()
    {
        return $this->diffObjects[$this->position];
    }

    /**
     * Iterator interface
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * Iterator interface
     *
     * @return int
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Iterator interface
     *
     * @return bool
     */
    public function valid()
    {
        return isset($this->diffObjects[$this->position]);
    }

    /**
     * Iterator interface
     */
    public function rewind()
    {
        $this->position = 0;
    }
}
