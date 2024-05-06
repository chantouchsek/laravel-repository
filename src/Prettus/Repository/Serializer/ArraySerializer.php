<?php

namespace Prettus\Repository\Serializer;

use League\Fractal\Pagination\CursorInterface;
use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Serializer\SerializerAbstract;

class ArraySerializer extends SerializerAbstract
{
    public function collection(?string $resourceKey, array $data): array
    {
        if (isset($resourceKey)) {
            return [$resourceKey => $data];
        }

        return $data;
    }

    public function item(?string $resourceKey, array $data): array
    {
        if (isset($resourceKey)) {
            return [$resourceKey => $data];
        }

        return $data;
    }

    public function null(): array
    {
        return [];
    }

    public function includedData(ResourceInterface $resource, array $data): array
    {
        return $data;
    }

    public function meta(array $meta): array
    {
        if (empty($meta)) {
            return [];
        }

        return ['meta' => $meta];
    }

    public function paginator(PaginatorInterface $paginator): array
    {
        $currentPage = $paginator->getCurrentPage();
        $lastPage = $paginator->getLastPage();

        $pagination = [
            'total' => $paginator->getTotal(),
            'count' => $paginator->getCount(),
            'perPage' => $paginator->getPerPage(),
            'currentPage' => $currentPage,
            'totalPages' => $lastPage,
            'hasMore' => $paginator->getPaginator()->hasMorePages(),
        ];

        $pagination['links'] = [];

        if ($currentPage > 1) {
            $pagination['links']['previous'] = $paginator->getUrl($currentPage - 1);
        }

        if ($currentPage < $lastPage) {
            $pagination['links']['next'] = $paginator->getUrl($currentPage + 1);
        }

        if (empty($pagination['links'])) {
            $pagination['links'] = (object) [];
        }

        return ['pagination' => $pagination];
    }

    public function cursor(CursorInterface $cursor): array
    {
        $cursor = [
            'current' => $cursor->getCurrent(),
            'prev' => $cursor->getPrev(),
            'next' => $cursor->getNext(),
            'count' => $cursor->getCount(),
        ];

        return ['cursor' => $cursor];
    }
}
