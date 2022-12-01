

## Alfred workflow 股票助手


### 基本使用


本扩展基于腾讯自选股扩展，添加自选股功能。直接下载[release](./股票助手.alfredworkflow)包，拖入Alfred中安装即可。

```
//查询某个股票的信息
gp zgpa

通过Command+Enter添加自选


//查询自选的实时信息
gp list

通过Command+Enter删除自选

```

## Changelog

[2022-12-01] 修改交互方式

- 关键字gp xxx 查询对应信息，通过Command+Enter可将对应条目添加到自选
- 关键字glist 查询自选，通过Command+Enter可将其从自选删除

[2022-11-24] 支持PHP8的修改