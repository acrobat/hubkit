<?php

declare(strict_types=1);

/*
 * This file is part of the HubKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace HubKit\Tests\Unit\Helper;

use HubKit\Helper\BranchAliasResolver;
use HubKit\Service\Git\GitConfig;
use HubKit\Tests\Handler\SymfonyStyleTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

final class BranchAliasResolverTest extends TestCase
{
    use SymfonyStyleTrait;

    public const FIXTURES_DIR = __DIR__.'/../../Fixtures';

    /** @var string */
    public $outputString = '';

    /** @test */
    public function it_get_alias_from_composer()
    {
        $style = $this->createStyle();
        $git = $this->givenGitAliasIsNotCalled();

        $resolver = new BranchAliasResolver($style, $git, self::FIXTURES_DIR.'/project_with_composer_alias');

        self::assertEquals('1.0-dev', $resolver->getAlias());
        self::assertEquals('composer.json "extra.branch-alias.dev-master"', $resolver->getDetectedBy());
        $this->assertNoOutput();
    }

    private function givenGitAliasIsNotCalled(): GitConfig
    {
        $gitProphecy = $this->prophesize(GitConfig::class);
        $gitProphecy->getGitConfig('branch.master.alias')->shouldNotBeCalled();

        return $gitProphecy->reveal();
    }

    /** @test */
    public function it_get_alias_as_stable_when_alias_is_pre_release()
    {
        $style = $this->createStyle();
        $git = $this->givenGitAliasIsNotCalled();

        $resolver = new BranchAliasResolver($style, $git, self::FIXTURES_DIR.'/project_with_composer_unstable_alias');

        self::assertEquals('1.0-dev', $resolver->getAlias());
        self::assertEquals('composer.json "extra.branch-alias.dev-master"', $resolver->getDetectedBy());
        $this->assertNoOutput();
    }

    /** @test */
    public function it_get_alias_from_git()
    {
        $style = $this->createStyle();
        $git = $this->givenGitAliasIs('2.0-dev');

        $resolver = new BranchAliasResolver($style, $git, self::FIXTURES_DIR);

        self::assertEquals('2.0-dev', $resolver->getAlias());
        self::assertEquals('Git config "branch.master.alias"', $resolver->getDetectedBy());
        $this->assertNoOutput();
    }

    /** @test */
    public function it_get_alias_from_git_if_composer_alias_is_absent()
    {
        $style = $this->createStyle();
        $git = $this->givenGitAliasIs('2.0-dev');

        $resolver = new BranchAliasResolver($style, $git, self::FIXTURES_DIR.'/project_with_composer');

        self::assertEquals('2.0-dev', $resolver->getAlias());
        self::assertEquals('Git config "branch.master.alias"', $resolver->getDetectedBy());
    }

    private function givenGitAliasIs(string $alias): GitConfig
    {
        $gitProphecy = $this->prophesize(GitConfig::class);
        $gitProphecy->getGitConfig('branch.master.alias')->willReturn($alias);

        return $gitProphecy->reveal();
    }

    /** @test */
    public function it_sets_alias_for_git()
    {
        $style = $this->createStyle(['3.0']);
        $git = $this->createGitConfigSpy();

        $resolver = new BranchAliasResolver($style, $git, self::FIXTURES_DIR);

        self::assertEquals('3.0-dev', $resolver->getAlias());
        self::assertEquals('Git config "branch.master.alias"', $resolver->getDetectedBy());
        self::assertEquals(['branch.master.alias' => '3.0-dev'], $git->configsSet);
    }

    private function createGitConfigSpy()
    {
        $git = new class() extends GitConfig {
            public $configsSet = [];

            public function __construct()
            {
                // Overwritten
            }

            public function getGitConfig(string $config, string $section = 'local', bool $all = false): string
            {
                Assert::assertEquals('branch.master.alias', $config);

                return '';
            }

            public function setGitConfig(string $config, $value, bool $overwrite = false, string $section = 'local'): void
            {
                $this->configsSet[$config] = $value;
            }
        };

        return $git;
    }

    /** @test */
    public function it_validates_alias_for_git()
    {
        $style = $this->createStyle(['stable', '4.0']);
        $git = $this->createGitConfigSpy();

        $resolver = new BranchAliasResolver($style, $git, self::FIXTURES_DIR);

        self::assertEquals('4.0-dev', $resolver->getAlias());
        self::assertEquals('Git config "branch.master.alias"', $resolver->getDetectedBy());
        self::assertEquals(['branch.master.alias' => '4.0-dev'], $git->configsSet);

        $this->assertOutputMatches('A branch alias consists of major and minor version without any prefix or suffix. like: 1.2');
    }
}
