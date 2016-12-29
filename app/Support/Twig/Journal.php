<?php
/**
 * Journal.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Support\Twig;


use Amount;
use FireflyIII\Models\Account;
use FireflyIII\Models\AccountType;
use FireflyIII\Models\Budget as ModelBudget;
use FireflyIII\Models\Category;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Support\CacheProperties;
use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

/**
 * Class Journal
 *
 * @package FireflyIII\Support\Twig
 */
class Journal extends Twig_Extension
{


    /**
     * @return Twig_SimpleFunction
     */
    public function getDestinationAccount(): Twig_SimpleFunction
    {
        return new Twig_SimpleFunction(
            'destinationAccount', function (TransactionJournal $journal) {
            $cache = new CacheProperties;
            $cache->addProperty($journal->id);
            $cache->addProperty('transaction-journal');
            $cache->addProperty('destination-account-string');
            if ($cache->has()) {
                return $cache->get();
            }

            $list  = TransactionJournal::destinationAccountList($journal);
            $array = [];
            /** @var Account $entry */
            foreach ($list as $entry) {
                if ($entry->accountType->type == AccountType::CASH) {
                    $array[] = '<span class="text-success">(cash)</span>';
                    continue;
                }
                $array[] = sprintf('<a title="%1$s" href="%2$s">%1$s</a>', e($entry->name), route('accounts.show', $entry->id));
            }
            $array  = array_unique($array);
            $result = join(', ', $array);
            $cache->store($result);

            return $result;
        }
        );
    }

    /**
     * @return array
     */
    public function getFilters(): array
    {
        $filters = [
            $this->typeIcon(),
        ];

        return $filters;
    }

    /**
     * @return array
     */
    public function getFunctions(): array
    {
        $functions = [
            $this->getSourceAccount(),
            $this->getDestinationAccount(),
            $this->journalBudgets(),
            $this->journalCategories(),
        ];

        return $functions;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName(): string
    {
        return 'FireflyIII\Support\Twig\Journals';
    }

    /**
     * @return Twig_SimpleFunction
     */
    public function getSourceAccount(): Twig_SimpleFunction
    {
        return new Twig_SimpleFunction(
            'sourceAccount', function (TransactionJournal $journal): string {

            $cache = new CacheProperties;
            $cache->addProperty($journal->id);
            $cache->addProperty('transaction-journal');
            $cache->addProperty('source-account-string');
            if ($cache->has()) {
                return $cache->get();
            }

            $list  = TransactionJournal::sourceAccountList($journal);
            $array = [];
            /** @var Account $entry */
            foreach ($list as $entry) {
                if ($entry->accountType->type == 'Cash account') {
                    $array[] = '<span class="text-success">(cash)</span>';
                    continue;
                }
                $array[] = sprintf('<a title="%1$s" href="%2$s">%1$s</a>', e($entry->name), route('accounts.show', $entry->id));
            }
            $array  = array_unique($array);
            $result = join(', ', $array);
            $cache->store($result);

            return $result;


        }
        );
    }

    /**
     * @return Twig_SimpleFunction
     */
    public function journalBudgets(): Twig_SimpleFunction
    {
        return new Twig_SimpleFunction(
            'journalBudgets', function (TransactionJournal $journal): string {
            $cache = new CacheProperties;
            $cache->addProperty($journal->id);
            $cache->addProperty('transaction-journal');
            $cache->addProperty('budget-string');
            if ($cache->has()) {
                return $cache->get();
            }


            $budgets = [];
            // get all budgets:
            foreach ($journal->budgets as $budget) {
                $budgets[] = sprintf('<a title="%1$s" href="%2$s">%1$s</a>', e($budget->name), route('budgets.show', $budget->id));
            }
            // and more!
            foreach ($journal->transactions as $transaction) {
                foreach ($transaction->budgets as $budget) {
                    $budgets[] = sprintf('<a title="%1$s" href="%2$s">%1$s</a>', e($budget->name), route('budgets.show', $budget->id));
                }
            }
            $string = join(', ', array_unique($budgets));
            $cache->store($string);

            return $string;


        }
        );
    }

    /**
     * @return Twig_SimpleFunction
     */
    public function journalCategories(): Twig_SimpleFunction
    {
        return new Twig_SimpleFunction(
            'journalCategories', function (TransactionJournal $journal): string {
            $cache = new CacheProperties;
            $cache->addProperty($journal->id);
            $cache->addProperty('transaction-journal');
            $cache->addProperty('category-string');
            if ($cache->has()) {
                return $cache->get();
            }
            $categories = [];
            // get all categories for the journal itself (easy):
            foreach ($journal->categories as $category) {
                $categories[] = sprintf('<a title="%1$s" href="%2$s">%1$s</a>', e($category->name), route('categories.show', $category->id));
            }
            if (count($categories) === 0) {
                $set = Category::distinct()->leftJoin('category_transaction', 'categories.id', '=', 'category_transaction.category_id')
                               ->leftJoin('transactions', 'category_transaction.transaction_id', '=', 'transactions.id')
                               ->leftJoin('transaction_journals', 'transactions.transaction_journal_id', '=', 'transaction_journals.id')
                               ->where('categories.user_id', $journal->user_id)
                               ->where('transaction_journals.id', $journal->id)
                               ->get(['categories.*']);
                /** @var Category $category */
                foreach ($set as $category) {
                    $categories[] = sprintf('<a title="%1$s" href="%2$s">%1$s</a>', e($category->name), route('categories.show', $category->id));
                }
            }

            $string = join(', ', array_unique($categories));
            $cache->store($string);

            return $string;
        }
        );
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) // it's 5.
     *
     * @return Twig_SimpleFilter
     */
    protected function typeIcon(): Twig_SimpleFilter
    {
        return new Twig_SimpleFilter(
            'typeIcon', function (TransactionJournal $journal): string {

            switch (true) {
                case $journal->isWithdrawal():
                    $txt = sprintf('<i class="fa fa-long-arrow-left fa-fw" title="%s"></i>', trans('firefly.withdrawal'));
                    break;
                case $journal->isDeposit():
                    $txt = sprintf('<i class="fa fa-long-arrow-right fa-fw" title="%s"></i>', trans('firefly.deposit'));
                    break;
                case $journal->isTransfer():
                    $txt = sprintf('<i class="fa fa-fw fa-exchange" title="%s"></i>', trans('firefly.transfer'));
                    break;
                case $journal->isOpeningBalance():
                    $txt = sprintf('<i class="fa-fw fa fa-ban" title="%s"></i>', trans('firefly.openingBalance'));
                    break;
                default:
                    $txt = '';
                    break;
            }

            return $txt;
        }, ['is_safe' => ['html']]
        );
    }

}
