本来是想试试hyperf的

不过他的代码提示真的是做得太差了

像
```
$model = new Model();
// laravel里面是可以
$model->create(); // 多条插入
$model->insert(); // 单条插入

// hyperf里面这些方法都有，毕竟继承自la的
// 不过la里面是用魔术方法来实现，通过注解的形式给phpstorm提供代码提示
// hy就没有这些，给人的感觉很不好

```